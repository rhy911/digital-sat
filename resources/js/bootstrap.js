import axios from 'axios';
window.axios = axios;

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
