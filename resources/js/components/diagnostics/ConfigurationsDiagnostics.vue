<template>
	<Panel header="Configuration" v-if="configs" class="border-none max-w-7xl mx-auto">
		<pre><div v-for="config in configs" class=" text-muted-color font-mono text-sm">{{ config }}</div>
		</pre>
	</Panel>
</template>
<script setup lang="ts">
import { ref } from "vue";
import Panel from "primevue/panel";
import DiagnosticsService from "@/services/diagnostics-service";

const configs = ref<string[] | undefined>(undefined);

function load() {
	DiagnosticsService.config().then((response) => {
		configs.value = response.data;
	});
}

load();
</script>
