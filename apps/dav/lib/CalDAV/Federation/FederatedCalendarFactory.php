<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAV\CalDAV\Federation;

use OCA\DAV\CalDAV\CalDavBackend;
use Psr\Log\LoggerInterface;

class FederatedCalendarFactory {

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly FederatedCalendarMapper $federatedCalendarMapper,
		private readonly FederatedCalendarSyncService $federatedCalendarService,
		private readonly CalDavBackend $caldavBackend,
	) {
	}

	public function createFederatedCalendar(array $calendarInfo): FederatedCalendar {
		return new FederatedCalendar(
			$this->logger,
			$this->federatedCalendarMapper,
			$this->federatedCalendarService,
			$this->caldavBackend,
			$calendarInfo,
		);
	}
}
