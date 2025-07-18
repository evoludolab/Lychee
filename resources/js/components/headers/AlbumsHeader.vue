<template>
	<ImportFromLink v-if="canUpload" v-model:visible="is_import_from_link_open" />
	<DropBox v-if="canUpload" v-model:visible="is_import_from_dropbox_open" :album-id="null" />
	<Toolbar
		:class="{
			'bg-transparent': props.config.is_header_bar_transparent,
			'bg-linear-to-b dark:from-surface-800 from-surface-50 via-75% light:via-surface-50/80 light:to-surface-50/20':
				props.config.is_header_bar_gradient,
		}"
		:pt:root:class="'w-full z-10 border-0 h-14 flex-nowrap relative rounded-none'"
		:pt:center:class="'absolute top-0 py-3 left-1/2 -translate-x-1/2 h-14'"
	>
		<template #start>
			<OpenLeftMenu />
		</template>

		<template #center>
			<template v-if="props.config.header_image_url === ''">
				<span class="lg:hidden font-bold text-shadow-sm text-shadow-black">
					{{ $t("gallery.albums") }}
				</span>
				<span
					class="hidden lg:block font-bold text-shadow-sm text-shadow-black text-sm lg:text-base text-center w-full"
					@click="is_metrics_open = !is_metrics_open"
					>{{ props.title }}</span
				>
			</template>
		</template>

		<template #end>
			<template v-if="props.user.id === null">
				<Button
					as="router-link"
					:to="{ name: 'login' }"
					severity="secondary"
					text
					:class="{
						'py-2 px-4 rounded-xl hidden xl:block': true,
						'dark:hover:text-surface-100': true,
						'hover:text-surface-800': true,
					}"
				>
					{{ $t("dialogs.login.signin") }}
				</Button>
				<Button
					v-if="is_registration_enabled"
					as="router-link"
					:to="{ name: 'register' }"
					severity="secondary"
					text
					:class="{
						'py-2 px-4 rounded-xl mr-12 block lg:mr-0': true,
						'dark:hover:text-surface-100 dark:border-surface-400 dark:hover:border-surface-100': true,
						'hover:text-surface-800 border-surface-500 hover:border-surface-800': true,
					}"
				>
					{{ $t("profile.register.signup") }}
				</Button>
			</template>
			<!-- Maybe logged in. -->
			<div class="hidden lg:block">
				<template v-for="item in menu">
					<template v-if="item.type === 'link'">
						<!-- @vue-ignore -->
						<Button as="router-link" :to="item.to" :icon="item.icon" class="border-none" severity="secondary" text />
					</template>
					<template v-else>
						<Button @click="item.callback" :icon="item.icon" class="border-none" severity="secondary" text />
					</template>
				</template>
				<!-- Not logged in. -->
				<BackLinkButton v-if="props.user.id === null" :config="props.config" />
			</div>
			<SpeedDial
				:model="menu"
				direction="down"
				class="top-0 ltr:mr-4 rtl:ml-4 absolute ltr:right-0 rtl:left-0 lg:hidden"
				:buttonProps="{ severity: 'help', rounded: true }"
			>
				<template #button="{ toggleCallback }">
					<Button text severity="secondary" class="border-none h-14" @click="toggleCallback" icon="pi pi-angle-double-down" />
				</template>
				<template #item="{ item, toggleCallback }">
					<template v-if="item.type === 'link'">
						<Button as="router-link" :to="item.to" :icon="item.icon" class="shadow-md shadow-black/25" severity="warn" rounded />
					</template>
					<template v-else>
						<Button @click="item.callback" :icon="item.icon" class="shadow-md shadow-black/25" severity="warn" rounded />
					</template>
				</template>
			</SpeedDial>
		</template>
	</Toolbar>
	<ContextMenu v-if="props.rights.can_upload" ref="addmenu" :model="addMenu">
		<template #item="{ item, props }">
			<Divider v-if="item.is_divider" />
			<a v-else v-ripple v-bind="props.action" @click="item.callback">
				<span :class="item.icon" />
				<span class="ltr:ml-2 rtl:mr-2">
					<!-- @vue-ignore -->
					{{ $t(item.label) }}
				</span>
			</a>
		</template>
	</ContextMenu>
	<div class="relative w-full h-[calc(100vh/2)] -mt-14 z-0" v-if="props.config.header_image_url !== ''">
		<img :src="props.config.header_image_url" class="object-cover h-full w-full" />
		<div class="absolute top-0 left-0 w-full h-full flex items-center justify-center px-20">
			<h1
				class="text-sm font-bold sm:text-lg md:text-3xl md:font-normal text-surface-0 uppercase text-center text-shadow-md text-shadow-black/25"
			>
				{{ props.title }}
			</h1>
		</div>
	</div>
</template>
<script setup lang="ts">
import Button from "primevue/button";
import SpeedDial from "primevue/speeddial";
import Toolbar from "primevue/toolbar";
import ContextMenu from "primevue/contextmenu";
import Divider from "primevue/divider";
import ImportFromLink from "@/components/modals/ImportFromLink.vue";
import { computed, ComputedRef } from "vue";
import { onKeyStroke } from "@vueuse/core";
import { useLycheeStateStore } from "@/stores/LycheeState";
import { isTouchDevice, shouldIgnoreKeystroke } from "@/utils/keybindings-utils";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import { useTogglablesStateStore } from "@/stores/ModalsState";
import { useContextMenuAlbumsAdd } from "@/composables/contextMenus/contextMenuAlbumsAdd";
import { useGalleryModals } from "@/composables/modalsTriggers/galleryModals";
import DropBox from "../modals/DropBox.vue";
import BackLinkButton from "./BackLinkButton.vue";
import OpenLeftMenu from "./OpenLeftMenu.vue";
import { useFavouriteStore } from "@/stores/FavouriteState";
import { useLeftMenuStateStore } from "@/stores/LeftMenuState";

const props = defineProps<{
	user: App.Http.Resources.Models.UserResource;
	title: string;
	rights: App.Http.Resources.Rights.RootAlbumRightsResource;
	config: {
		is_search_accessible: boolean;
		show_keybinding_help_button: boolean;
		back_button_enabled: boolean;
		back_button_text: string;
		back_button_url: string;
		header_image_url: string;
		is_header_bar_transparent: boolean;
		is_header_bar_gradient: boolean;
	};
	hasHidden: boolean;
}>();

const emits = defineEmits<{
	refresh: [];
	help: [];
}>();

const leftMenuStore = useLeftMenuStateStore();
const lycheeStore = useLycheeStateStore();
const togglableStore = useTogglablesStateStore();
const favourites = useFavouriteStore();

const { dropbox_api_key, is_favourite_enabled, is_se_preview_enabled, is_live_metrics_enabled, is_registration_enabled } = storeToRefs(lycheeStore);
const { is_login_open, is_upload_visible, is_create_album_visible, is_create_tag_album_visible, is_metrics_open } = storeToRefs(togglableStore);

const router = useRouter();

const {
	toggleCreateAlbum,
	toggleCreateTagAlbum,
	is_import_from_link_open,
	toggleImportFromLink,
	is_import_from_dropbox_open,
	toggleImportFromDropbox,
	toggleUpload,
} = useGalleryModals(togglableStore);

const { addmenu, addMenu } = useContextMenuAlbumsAdd(
	{
		toggleUpload: toggleUpload,
		toggleCreateAlbum: toggleCreateAlbum,
		toggleImportFromLink: toggleImportFromLink,
		toggleImportFromDropbox: toggleImportFromDropbox,
		toggleCreateTagAlbum: toggleCreateTagAlbum,
	},
	dropbox_api_key,
);

const canUpload = computed(() => props.user.id !== null);

function openAddMenu(event: Event) {
	addmenu.value.show(event);
}

function openHelp() {
	emits("help");
}

function openSearch() {
	router.push({ name: "search" });
}

onKeyStroke("n", () => !shouldIgnoreKeystroke() && props.rights.can_upload && (is_create_album_visible.value = true));
onKeyStroke("u", () => !shouldIgnoreKeystroke() && props.rights.can_upload && (is_upload_visible.value = true));
onKeyStroke("/", () => !shouldIgnoreKeystroke() && props.config.is_search_accessible && openSearch());

// on key stroke escape:
// 1. lose focus
// 2. close modals
// 3. go back
onKeyStroke("escape", () => {
	// 1. lose focus
	if (document.activeElement instanceof HTMLElement) {
		document.activeElement.blur();
		return;
	}

	// 2. close modals
	if (is_login_open.value) {
		is_login_open.value = false;
		return;
	}

	if (is_upload_visible.value) {
		is_upload_visible.value = false;
		return;
	}
	if (is_create_album_visible.value) {
		is_create_album_visible.value = false;
		return;
	}
	if (is_create_tag_album_visible.value) {
		is_create_tag_album_visible.value = false;
		return;
	}

	leftMenuStore.left_menu_open = false;
});

type Link = {
	type: "link";
	to: { name: string };
};
type Callback = {
	type: "fn";
	callback: () => void;
};
type Item = {
	icon: string;
	if: boolean;
};
type MenuRight = (Item & Link & { key: string }) | (Item & Callback & { key: string });

const menu = computed(() =>
	[
		{
			to: { name: "favourites" },
			type: "link",
			icon: "pi pi-heart",
			if: props.user.id !== null && is_favourite_enabled.value && (favourites.photos?.length ?? 0) > 0,
			key: "favourites",
		},
		{
			icon: "pi pi-search",
			type: "fn",
			callback: openSearch,
			if: props.config.is_search_accessible,
			key: "search",
		},
		{
			icon: "pi pi-bell",
			type: "fn",
			callback: () => (is_metrics_open.value = true),
			if: is_live_metrics_enabled.value && props.rights.can_see_live_metrics,
			key: "metrics",
		},
		{
			icon: "pi pi-bell text-primary-emphasis",
			type: "fn",
			callback: () => (is_metrics_open.value = true),
			if: is_se_preview_enabled.value && props.rights.can_see_live_metrics,
			key: "se_preview",
		},
		{
			icon: "pi pi-question-circle",
			type: "fn",
			callback: openHelp,
			if: !isTouchDevice() && props.user.id !== null && props.config.show_keybinding_help_button && document.body.scrollWidth > 800,
			key: "help",
		},
		{
			icon: "pi pi-plus",
			type: "fn",
			callback: openAddMenu,
			if: props.rights.can_upload,
			key: "add_menu",
		},
		{
			icon: "pi pi-eye-slash",
			type: "fn",
			callback: () => (lycheeStore.are_nsfw_visible = false),
			if: isTouchDevice() && props.hasHidden && lycheeStore.are_nsfw_visible,
			key: "hide_nsfw",
		},
		{
			icon: "pi pi-eye",
			type: "fn",
			callback: () => (lycheeStore.are_nsfw_visible = true),
			if: isTouchDevice() && props.hasHidden && !lycheeStore.are_nsfw_visible,
			key: "show_nsfw",
		},
	].filter((item) => item.if),
) as ComputedRef<MenuRight[]>;
</script>
