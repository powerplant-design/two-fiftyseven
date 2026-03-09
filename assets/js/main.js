// Two Fifty Seven — main entry point
import '../css/tailwind.css';
import '../css/styles.scss';
import 'locomotive-scroll/dist/locomotive-scroll.css';

import { initColorTheme } from './modules/color-theme.js';
import { initScroll }     from './modules/scroll.js';
import { initTransitions } from './modules/transitions.js';

initColorTheme();
initScroll();
initTransitions();
