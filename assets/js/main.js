// Two Fifty Seven — main entry point
import '../css/tailwind.css';
import '../css/styles.scss';
import 'locomotive-scroll/dist/locomotive-scroll.css';

import { initColorTheme }    from './modules/color-theme.js';
import { initScroll }        from './modules/scroll.js';
import { initMarquee }       from './modules/marquee.js';
import { initTransitions }   from './modules/transitions.js';
import { initFooter }        from './modules/footer.js';
import { initHeader }        from './modules/header.js';
import { initStackedCards }  from './modules/stacked-cards.js';
import { initFaq }           from './modules/faq.js';
import { initEventsArchive } from './modules/events-archive.js';
import { initCptArchive }    from './modules/cpt-archive.js';
import { initImpact }        from './modules/impact.js';

initColorTheme();
initScroll();
initMarquee();
initTransitions();
initFooter();
initHeader();
initStackedCards();
initFaq();
initEventsArchive();
initCptArchive();
initImpact();
