import AutoUuid from './components/fieldtypes/AutoUuid.vue';
import { initCp } from './cp.js';

Statamic.booting(() => {
  Statamic.component('auto_uuid-fieldtype', AutoUuid);
  initCp();
});
