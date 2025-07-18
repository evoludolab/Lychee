<template>
	<Dialog v-model:visible="visible" modal :dismissable-mask="true" pt:root:class="border-none" @hide="closeCallback">
		<template #container="{ closeCallback }">
			<div class="flex flex-col gap-4 bg-gradient-to-b from-bg-300 to-bg-400 relative max-w-md w-full rounded-md text-muted-color">
				<div class="p-9">
					<p class="mb-5 text-muted-color-emphasis text-base">{{ $t("dialogs.import_from_link.instructions") }}</p>
					<form>
						<div class="my-3 first:mt-0 last:mb-0" dir="ltr">
							<Textarea
								id="links"
								v-model="urls"
								:rows="5"
								:cols="30"
								placeholder="https://&#10;https://&#10;..."
								:invalid="!isValidInput && urls.length > 0"
							/>
						</div>
					</form>
				</div>
				<div class="flex justify-center">
					<Button
						severity="secondary"
						class="w-full font-bold border-none rounded-none ltr:rounded-bl-xl rtl:rounded-br-xl"
						@click="closeCallback"
					>
						{{ $t("dialogs.button.cancel") }}
					</Button>
					<Button
						severity="contrast"
						class="w-full font-bold border-none rounded-none ltr:rounded-br-xl rtl:rounded-bl-xl"
						@click="submit"
						:disabled="!isValidInput || urls.length === 0"
					>
						{{ $t("dialogs.import_from_link.import") }}
					</Button>
				</div>
			</div>
		</template>
	</Dialog>
</template>
<script setup lang="ts">
import { computed, ref, Ref } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import PhotoService from "@/services/photo-service";
import { useRouter } from "vue-router";
import { usePhotoRoute } from "@/composables/photo/photoRoute";
import Textarea from "../forms/basic/Textarea.vue";
import AlbumService from "@/services/album-service";

const visible = defineModel("visible", { default: false }) as Ref<boolean>;
const emits = defineEmits<{ refresh: [] }>();

const router = useRouter();
const { getParentId } = usePhotoRoute(router);

const urls = ref<string>("");

function submit() {
	PhotoService.importFromUrl(urls.value.split("\n"), getParentId() ?? null).then(() => {
		urls.value = "";
		visible.value = false;
		// Clear cache for the parent album to ensure the new photos are displayed
		AlbumService.clearCache(getParentId() ?? "unsorted");
		emits("refresh");
	});
}

const isValidInput = computed(() => urls.value.split("\n").every((url) => url.match(/^https?:\/\/.+/)));

function closeCallback() {
	visible.value = false;
}
</script>
