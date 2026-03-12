<script setup>
import { Fieldtype } from '@statamic/cms';
import { onMounted } from 'vue';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update } = Fieldtype.use(emit, props);
defineExpose(expose);

onMounted(() => {
    if (!props.value) {
        update(crypto.randomUUID());
    }
});
</script>

<template>
    <input type="hidden" :value="value" :data-visual-id="value" />
</template>
