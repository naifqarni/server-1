<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="admin-devices-sessions" class="section">
		<h2>{{ t('settings', 'Devices & sessions') }}</h2>
		<p class="settings-hint">
			{{ t('settings', 'Manage devices and sessions for a specific user.') }}
		</p>

		<form class="user-search-form" @submit.prevent="searchUser">
			<NcTextField
				v-model="username"
				:label="t('settings', 'Username')"
				show-trailing-button
				:trailing-button-label="t('settings', 'Search')"
				@trailing-button-click="searchUser" />
		</form>

		<div v-if="hasSearched">
			<h3 v-if="usernameDisplay">
				{{ t('settings', 'Sessions for {user}', { user: usernameDisplay }) }}
			</h3>
			<AuthTokenList v-if="hasTokens" />
			<div v-else class="empty-list">
				{{ t('settings', 'No sessions found for this user.') }}
			</div>
		</div>
	</div>
</template>

<script lang="ts">
import { defineComponent, ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AuthTokenList from './AuthTokenList.vue'
import { useAuthTokenStore } from '../store/authtoken.ts'

export default defineComponent({
	name: 'AdminAuthTokenSection',
	components: {
		NcTextField,
		AuthTokenList,
	},
	setup() {
		const store = useAuthTokenStore()
		const username = ref('')
		const usernameDisplay = ref('')
		const hasSearched = ref(false)

		return {
			store,
			username,
			usernameDisplay,
			hasSearched,
		}
	},
	computed: {
		hasTokens() {
			return this.store.tokens.length > 0
		},
	},
	methods: {
		t,
		async searchUser() {
			if (!this.username) {
				return
			}

			this.usernameDisplay = this.username
			this.hasSearched = true

			// Set Base URL for this user
			// We encode the username to be safe in URL
			const url = generateUrl('/settings/admin/users/' + encodeURIComponent(this.username) + '/authtokens')
			this.store.setBaseUrl(url)

			// Load tokens
			await this.store.loadTokens()
		},
	},
})
</script>

<style scoped>
.user-search-form {
	margin-bottom: 20px;
	max-width: 300px;
}
.empty-list {
    margin-top: 10px;
    color: var(--color-text-maxcontrast);
}
</style>
