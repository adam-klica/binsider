// Generates the block theme's templates/*.html and parts/*.html from one place,
// so every card/section uses identical, valid block markup.
//   node tools/build-templates.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', 'theme', 'binsider');

// ---------- block markup helpers ----------
const json = (o) => (o && Object.keys(o).length ? ' ' + JSON.stringify(o) : '');
const self = (name, attrs) => `<!-- wp:${name}${json(attrs)} /-->`;
const group = (cls, inner, tag = 'div') =>
  `<!-- wp:group ${JSON.stringify({ ...(tag !== 'div' ? { tagName: tag } : {}), className: cls, layout: { type: 'default' } })} -->\n` +
  `<${tag} class="wp-block-group ${cls}">${inner}</${tag}>\n<!-- /wp:group -->`;
const heading = (text, level = 2, cls = '') =>
  `<!-- wp:heading${json({ ...(level !== 2 ? { level } : {}), ...(cls ? { className: cls } : {}) })} -->\n` +
  `<h${level} class="wp-block-heading${cls ? ' ' + cls : ''}">${text}</h${level}>\n<!-- /wp:heading -->`;
const para = (html, cls = '') =>
  `<!-- wp:paragraph${json(cls ? { className: cls } : {})} -->\n<p${cls ? ` class="${cls}"` : ''}>${html}</p>\n<!-- /wp:paragraph -->`;
const part = (slug, area) => self('template-part', { slug, area, tagName: area === 'uncategorized' ? 'div' : area });
const j = (...xs) => xs.join('\n');

let queryId = 0;
function query(q, cls, inner) {
  queryId += 1;
  const attrs = {
    queryId,
    query: {
      perPage: q.perPage ?? 4, pages: 0, offset: q.offset ?? 0, postType: 'post', order: 'desc', orderBy: 'date',
      author: '', search: '', exclude: [], sticky: '', inherit: !!q.inherit,
      ...(q.binsider ? { binsider: q.binsider } : {}),
      ...(q.cat ? { binsiderCat: q.cat } : {}),
    },
    className: `bi-q ${cls}`,
  };
  return `<!-- wp:query ${JSON.stringify(attrs)} -->\n<div class="wp-block-query bi-q ${cls}">${inner}</div>\n<!-- /wp:query -->`;
}

// One card markup everywhere; CSS decides what each list variant shows.
// `sizes` names a class that binsider-core maps to the image's `sizes` attribute (includes/performance.php).
function card({ level = 3, excerpt = false, image = 'large', sizes = 'col' } = {}) {
  return group(
    'bi-card',
    j(
      '',
      self('post-featured-image', { isLink: true, aspectRatio: '3/2', sizeSlug: image, className: `bi-card__img bi-sz-${sizes}` }),
      group(
        'bi-card__body',
        j(
          '',
          self('binsider/post-label'),
          self('post-title', { isLink: true, level, className: 'bi-card__title' }),
          excerpt ? self('post-excerpt', { excerptLength: 30, className: 'bi-card__excerpt' }) : '',
          self('binsider/post-byline'),
          '',
        ).replace(/\n\n+/g, '\n'),
      ),
      '',
    ),
    'article',
  );
}
const template = (inner, cls = 'bi-cards') => `<!-- wp:post-template {"className":"${cls}"} -->\n${inner}\n<!-- /wp:post-template -->`;
const noResults = (text) =>
  `<!-- wp:query-no-results -->\n${para(text, 'bi-empty')}\n<!-- /wp:query-no-results -->`;
const pagination = () =>
  `<!-- wp:query-pagination {"paginationArrow":"arrow","className":"bi-pagination","layout":{"type":"flex","justifyContent":"space-between"}} -->\n` +
  `${self('query-pagination-previous', { label: 'Novije' })}\n${self('query-pagination-numbers')}\n${self('query-pagination-next', { label: 'Starije' })}\n` +
  `<!-- /wp:query-pagination -->`;

const sectionTitle = (slug, label) => self('binsider/section-title', label ? { slug, label } : { slug });
const plainTitle = (text) => group('bi-section-title', heading(text, 2, 'bi-section-title__h'));

const page = (main) => j(part('header', 'header'), group('bi-main', main, 'main'), part('footer', 'footer'), '');

// ---------- parts ----------
const searchBlock = (cls = 'bi-search') => self('search', { label: 'Pretraga', showLabel: false, placeholder: 'Pretraži vijesti…', buttonText: 'Traži', className: cls });

// Phones only (CSS): a magnifier that unfolds the search field (theme.js) and the tip link from the hidden top bar.
const mastActions =
  `<!-- wp:html -->\n<div class="bi-mast-actions"><button class="bi-search-toggle" type="button" aria-expanded="false" aria-label="Pretraga">` +
  `<svg class="bi-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M15.5 15.5 21 21" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>` +
  `</button><a class="bi-mast-tip" href="/posalji-vijest/">Pošalji vijest</a></div>\n<!-- /wp:html -->`;

// Header: kilim band, top bar, masthead, sticky section bar (with a mini mark), ticker.
// The header wrappers are display:contents in CSS so the section bar can stick to the viewport.
const header = group(
  'bi-header',
  j(
    '',
    group('bi-kilim', ''),
    group('bi-topbar', group('bi-wrap bi-topbar__in', j('', self('binsider/date'), group('bi-topbar__right', j('', self('binsider/social'), para('<a href="/posalji-vijest/">Pošalji vijest</a>', 'bi-topbar__cta'), '')), ''))),
    group('bi-masthead', group('bi-wrap bi-masthead__in', j('', self('binsider/logo', { variant: 'horizontal' }), para('Nezavisni portal Bošnjaka Crne Gore, regiona i dijaspore', 'bi-masthead__tag'), mastActions, searchBlock(), ''))),
    group('bi-navbar', group('bi-wrap bi-navbar__in', j('', self('binsider/logo', { variant: 'mark' }), self('binsider/sections-nav'), ''))),
    self('binsider/ticker'),
    '',
  ),
);

const footer = group(
  'bi-footer',
  j(
    '',
    group('bi-kilim', ''),
    group(
      'bi-wrap bi-footer__grid',
      j(
        '',
        group('bi-footer__brand', j('', self('binsider/logo', { variant: 'horizontal', color: 'light' }), para('Nezavisni portal Bošnjaka Crne Gore, regiona i dijaspore. Vijesti, analize, intervjui, video i foto prilozi.', 'bi-footer__about'), self('binsider/social'), '')),
        group('bi-footer__col', j('', heading('Rubrike', 2, 'bi-footer__h'), self('binsider/sections-nav', { variant: 'list' }), '')),
        group('bi-footer__col', j('', heading('Portal', 2, 'bi-footer__h'), self('binsider/pages-nav'), '')),
        '',
      ),
    ),
    group('bi-wrap bi-footer__bottom', j('', para('© Bošnjački Insajder · www.binsider.me · Sva prava zadržana.', ''), para('Preuzimanje sadržaja dozvoljeno uz navođenje izvora i link ka originalnom tekstu.', ''), '')),
    '',
  ),
  'footer',
);

// ---------- front page ----------
// Region blocks use different layouts so the page has rhythm instead of five identical rows.
const regions = [
  ['crna-gora', 'bi-q--feature', 5],
  ['sandzak', 'bi-q--row', 4],
  ['bosna-i-hercegovina', 'bi-q--feature is-flip', 5],
  ['dijaspora', 'bi-q--row', 4, 'bi-tint'],
  ['svijet', 'bi-q--row', 4],
];
const topics = [
  ['politika-i-drustvo'], ['ekonomija-i-biznis'], ['intervjui-i-stavovi'],
  ['kultura-i-tradicija'], ['vjera-i-zajednica'], ['nauka-i-obrazovanje'],
];
const section = (cls, inner) => group(`bi-wrap bi-section bi-reveal ${cls}`.trim(), inner, 'section');
const band = (cls, inner) => group(`bi-band ${cls}`, group('bi-wrap bi-section bi-reveal', inner), 'section');

const tipBox = group(
  'bi-tip',
  j(
    '',
    heading('Imate vijest?', 2, 'bi-tip__h'),
    para('Pošaljite nam informaciju, fotografiju ili video. Izvore štitimo.', 'bi-tip__text'),
    self('binsider/chat-links'),
    '',
  ),
);

const front = page(
  j(
    '',
    // 1. Lead story (photo with headline over it) + 4 secondary
    group(
      'bi-wrap bi-top',
      j(
        '',
        query({ perPage: 1, binsider: 'lead' }, 'bi-q--lead', template(card({ level: 2, excerpt: true, image: 'full', sizes: 'lead' }))),
        query({ perPage: 4, binsider: 'secondary' }, 'bi-q--side', template(card({ image: 'medium', sizes: 'thumb' }))),
        '',
      ),
      'section',
    ),
    // 2. Latest feed + most read
    group(
      'bi-wrap bi-split bi-reveal',
      j(
        '',
        group('bi-split__main', j('', plainTitle('Najnovije'), query({ perPage: 8, offset: 4, binsider: 'secondary' }, 'bi-q--list', template(card({ image: 'medium_large', excerpt: true, sizes: 'list' }))), para('<a href="/najnovije/">Sve najnovije vijesti</a>', 'bi-more'), '')),
        group('bi-split__side', j('', self('binsider/most-read', { count: 5 }), tipBox, group('bi-follow', j('', heading('Pratite nas', 2, 'bi-follow__h'), para('Video prilozi, intervjui i foto galerije svakog dana na našim mrežama.', ''), self('binsider/social'), '')), '')),
        '',
      ),
      'section',
    ),
    // 3. Regions
    ...regions.map(([slug, cls, n, wrap]) => {
      const inner = j('', sectionTitle(slug), query({ perPage: n, cat: slug }, cls, template(card({ excerpt: true, sizes: cls.includes('feature') ? 'half' : 'col' }))), '');
      return wrap ? band(wrap, inner) : section('', inner);
    }),
    // 4. Video band
    band('bi-dark', j('', sectionTitle('video', 'Video'), query({ perPage: 4, cat: 'video' }, 'bi-q--row bi-q--video', template(card({ image: 'medium_large' }))), '')),
    // 5. Topics in columns of three
    group('bi-wrap bi-cols bi-reveal', j('', ...topics.map(([slug]) => group('bi-col', j('', sectionTitle(slug), query({ perPage: 4, cat: slug }, 'bi-q--col', template(card({ image: 'medium_large' }))), ''))), ''), 'section'),
    // 6. Sport + photo mosaic
    section('', j('', sectionTitle('sport-i-zanimljivosti'), query({ perPage: 4, cat: 'sport-i-zanimljivosti' }, 'bi-q--row', template(card({ image: 'medium_large' }))), '')),
    section('', j('', sectionTitle('foto-i-dokumenti'), query({ perPage: 4, cat: 'foto-i-dokumenti' }, 'bi-q--mosaic', template(card({ image: 'large', sizes: 'half' }))), '')),
    '',
  ),
);

// ---------- archive / search / index ----------
const archiveList = (inherit = true) =>
  query({ perPage: 12, inherit }, 'bi-q--archive', j('', template(card({ excerpt: true, sizes: 'half' })), pagination(), noResults('Nema članaka za prikaz.'), ''));

const archive = page(
  j(
    '',
    group('bi-wrap bi-archive-head', j('', self('query-title', { type: 'archive', showPrefix: false, className: 'bi-archive-head__title' }), self('term-description', { className: 'bi-archive-head__desc' }), '')),
    group('bi-wrap bi-archive', archiveList()),
    '',
  ),
);

const search = page(
  j(
    '',
    group('bi-wrap bi-archive-head', j('', self('query-title', { type: 'search', className: 'bi-archive-head__title' }), searchBlock('bi-search bi-search--big'), '')),
    group('bi-wrap bi-archive', archiveList()),
    '',
  ),
);

// Index is also used by the "Najnovije" page (/najnovije/ is the posts page if set) and as a fallback.
const index = page(
  j(
    '',
    group('bi-wrap bi-archive-head', heading('Najnovije vijesti', 1, 'bi-archive-head__title')),
    group('bi-wrap bi-archive', archiveList()),
    '',
  ),
);

// ---------- single ----------
const single = page(
  j(
    '',
    group(
      'bi-wrap bi-article',
      j(
        '',
        group('bi-article__head', j('', self('binsider/post-label'), self('post-title', { level: 1, className: 'bi-article__title' }), self('binsider/post-lead'), self('binsider/post-byline', { mode: 'full' }), '')),
        self('binsider/featured'),
        group(
          'bi-article__layout',
          j(
            '',
            group('bi-article__main', j('', self('post-content', { className: 'bi-content', layout: { type: 'default' } }), self('post-terms', { term: 'post_tag', prefix: 'Tagovi: ', className: 'bi-tags' }), self('binsider/share'), self('binsider/author-box'), '')),
            group('bi-article__side', self('binsider/most-read', { count: 5 }), 'aside'),
            '',
          ),
        ),
        '',
      ),
      'article',
    ),
    group('bi-wrap bi-section bi-related bi-reveal', j('', plainTitle('Povezano'), query({ perPage: 4, binsider: 'related' }, 'bi-q--row', template(card({ image: 'medium_large' }))), ''), 'section'),
    '',
  ),
);

// ---------- page ----------
const pageTpl = page(
  group(
    'bi-wrap bi-page',
    j('', self('post-title', { level: 1, className: 'bi-page__title' }), self('post-content', { className: 'bi-content', layout: { type: 'default' } }), ''),
    'article',
  ),
);

// ---------- 404 ----------
const notFound = page(
  j(
    '',
    group(
      'bi-wrap bi-404',
      j(
        '',
        para('404', 'bi-404__code'),
        heading('Stranica nije pronađena', 1, 'bi-404__title'),
        para('Link je možda zastario ili je članak premješten. Pokušajte pretragu ili pogledajte najnovije vijesti.', ''),
        searchBlock('bi-search bi-search--big'),
        '',
      ),
    ),
    group('bi-wrap bi-section', j('', plainTitle('Najnovije'), query({ perPage: 4 }, 'bi-q--row', template(card({ image: 'medium_large' }))), ''), 'section'),
    '',
  ),
);

const out = {
  'parts/header.html': header + '\n',
  'parts/footer.html': footer + '\n',
  'templates/front-page.html': front,
  'templates/index.html': index,
  'templates/archive.html': archive,
  'templates/search.html': search,
  'templates/single.html': single,
  'templates/page.html': pageTpl,
  'templates/404.html': notFound,
};
for (const [file, content] of Object.entries(out)) {
  fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
  fs.writeFileSync(path.join(root, file), content);
}
console.log('written:', Object.keys(out).join(', '));
