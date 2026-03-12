import AutoUuid from './components/fieldtypes/AutoUuid.vue'

Statamic.booting(() => {
  Statamic.component('auto_uuid-fieldtype', AutoUuid)
})
