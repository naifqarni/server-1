<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Settings\Controller;

use OC\Authentication\Token\INamedToken;
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\RemoteWipe;
use OCA\Settings\Activity\Provider;
use OCP\Activity\IManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\Exceptions\ExpiredTokenException;
use OCP\Authentication\Exceptions\InvalidTokenException;
use OCP\Authentication\Exceptions\WipeTokenException;
use OCP\Authentication\Token\IToken;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class AdminAuthSettingsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IProvider $tokenProvider,
		private IUserManager $userManager,
		private RemoteWipe $remoteWipe,
		private IManager $activityManager,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return JSONResponse
	 */
	#[AdminRequired]
	public function index(string $userId): JSONResponse {
		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$tokens = $this->tokenProvider->getTokenByUser($userId);

		$result = array_map(function (IToken $token) {
			$data = $token->jsonSerialize();
			$data['canDelete'] = true;
			// Admins can revoke/wipe but renaming might be personal?
			// Let's allow admins to see everything but maybe restrict rename if not needed.
			// For consistency with AuthToken.vue which checks 'canRename', let's set it.
			$data['canRename'] = $token instanceof INamedToken && $data['type'] !== IToken::WIPE_TOKEN;
			$data['current'] = false; // Admin is viewing another user, so never current session
			return $data;
		}, $tokens);

		return new JSONResponse($result);
	}

	/**
	 * @param string $userId
	 * @param int $id
	 * @return JSONResponse
	 */
	#[AdminRequired]
	public function destroy(string $userId, int $id): JSONResponse {
		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			$token = $this->findTokenByIdAndUser($id, $userId);
		} catch (WipeTokenException $e) {
			$token = $e->getToken();
		} catch (InvalidTokenException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$this->tokenProvider->invalidateTokenById($userId, $token->getId());
		$this->publishActivity(Provider::APP_TOKEN_DELETED, $token->getId(), ['name' => $token->getName()], $userId);

		return new JSONResponse([]);
	}

	/**
	 * @param string $userId
	 * @param int $id
	 * @return JSONResponse
	 */
	#[AdminRequired]
	#[PasswordConfirmationRequired]
	public function wipe(string $userId, int $id): JSONResponse {
		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			$token = $this->findTokenByIdAndUser($id, $userId);
		} catch (InvalidTokenException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!$this->remoteWipe->markTokenForWipe($token)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse([]);
	}

	/**
	 * Find a token by given id and check if uid for current session belongs to this token
	 *
	 * @param int $id
	 * @param string $userId
	 * @return IToken
	 * @throws InvalidTokenException
	 */
	private function findTokenByIdAndUser(int $id, string $userId): IToken {
		try {
			$token = $this->tokenProvider->getTokenById($id);
		} catch (ExpiredTokenException $e) {
			$token = $e->getToken();
		}
		if ($token->getUID() !== $userId) {
			// Using OcInvalidTokenException as in original controller might be tricky if not imported or internal
			// Just throw generic InvalidTokenException
			throw new InvalidTokenException('Token does not belong to user');
		}
		return $token;
	}

	/**
	 * @param string $subject
	 * @param int $id
	 * @param array $parameters
	 * @param string $affectedUser
	 */
	private function publishActivity(string $subject, int $id, array $parameters, string $affectedUser): void {
		$event = $this->activityManager->generateEvent();
		$event->setApp('settings')
			->setType('security')
			->setAffectedUser($affectedUser)
			// Author is the admin (current user), but usually activity is logged as the user doing it.
			// If we want to show it in the user's stream, affectedUser is crucial.
			// Author will be automatically set to current user (admin).
			->setSubject($subject, $parameters)
			->setObject('app_token', $id, 'App Password');

		try {
			$this->activityManager->publish($event);
		} catch (\Exception $e) {
			$this->logger->warning('could not publish activity', ['exception' => $e]);
		}
	}
}
