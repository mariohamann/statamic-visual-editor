import StatamicVisualEditor from './components/fieldtypes/StatamicVisualEditor.vue'

Statamic.booting(() => {
    Statamic.component('statamic_visual_editor-fieldtype', StatamicVisualEditor)
})
