<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="security" class="section">
		<h2>{{ t('settings', 'Devices & sessions', {}, undefined, { sanitize: false }) }}</h2>
		<p class="settings-hint hidden-when-empty">
			{{ t('settings', 'Web, desktop and mobile clients currently logged in to your account.') }}
		</p>
		<AuthTokenList />
		<AuthTokenSetup v-if="canCreateToken" />
	</div>
</template>

<script lang="ts">
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import AuthTokenList from './AuthTokenList.vue'
import AuthTokenSetup from './AuthTokenSetup.vue'
import { useAuthTokenStore } from '../store/authtoken.ts'
import { generateUrl } from '@nextcloud/router'

export default defineComponent({
	name: 'AuthTokenSection',
	components: {
		AuthTokenList,
		AuthTokenSetup,
	},

	setup() {
		const store = useAuthTokenStore()
		store.setBaseUrl(generateUrl('/settings/personal/authtokens'))
		store.tokens = loadState('settings', 'app_tokens', [])
		return {}
	},

	data() {
		return {
			canCreateToken: loadState('settings', 'can_create_app_token'),
		}
	},

	methods: {
		t,
	},
})
</script>