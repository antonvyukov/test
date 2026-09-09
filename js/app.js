import {
  GROUPS,
  DEFAULT_SELECTION,
  resolve,
  generateMarkdown,
  cssVars,
  serialize,
  parseHash,
  randomSelection,
  isLocked,
  isInferred,
  tagLabel,
} from './core.js'

const treeEl = document.querySelector('#tree')
const pageEl = document.querySelector('#page')
const browserEl = document.querySelector('#browser')
const mdEl = document.querySelector('#markdown')
const nameEl = document.querySelector('#preview-name')
const hoverEl = document.querySelector('#preview-hover')
const urlEl = document.querySelector('#browser-url')
const viewportEl = document.querySelector('#preview-viewport')

let locked = parseHash(location.hash)
let hover = null

function current() {
  return resolve(locked, hover)
}

function applyVars(el, t) {
  const vars = cssVars(t)
  for (const [k, v] of Object.entries(vars)) el.style.setProperty(k, v)
  el.classList.toggle('is-outline', t.sel.button === 'outline')
  el.classList.toggle('is-ghost', t.sel.button === 'ghost')
  el.classList.toggle('hide-media', t.sel.image === 'none')
}

function media(t) {
  if (t.sel.image === 'none') return ''
  return `<div class="media ${t.sel.image}" aria-hidden="true"></div>`
}

function nav(t, links) {
  return `<header class="nav wrap">
    <div class="nav-brand">${t.brand}</div>
    <div class="nav-links">${links.map((l) => `<span>${l}</span>`).join('')}</div>
    <span class="btn btn-primary">${t.voice.cta}</span>
  </header>`
}

function hero(t, extra = '') {
  return `<section class="hero is-${t.sel.hero} wrap">
    <div>
      <p class="kicker">${t.mood.kicker}</p>
      <h1>${t.mood.headline}</h1>
      <p class="lead">${t.voice.lead}${extra}</p>
      <div class="hero-actions">
        <span class="btn btn-primary">${t.voice.cta}</span>
        <span class="btn quiet">${t.voice.secondary}</span>
      </div>
    </div>
    ${media(t)}
  </section>`
}

function logos() {
  return `<div class="wrap"><div class="logos"><span>ORBIT</span><span>FIELD</span><span>QUILL</span><span>NINE</span><span>ARC</span></div></div>`
}

function features() {
  const items = [
    ['Сетка', 'Одна ширина, один ритм. Блоки не вылезают из токенов.'],
    ['Тип', 'Пара шрифтов на всю страницу. Иерархия только размером.'],
    ['Правила', 'Что можно и чего нельзя — сразу в design.md.'],
  ]
  return `<section class="section wrap">
    <h2 class="section-title">Как это держит страницу</h2>
    <div class="grid-3">${items
      .map(
        ([h, p]) => `<article class="card"><h3>${h}</h3><p>${p}</p></article>`,
      )
      .join('')}</div>
  </section>`
}

function quote() {
  return `<section class="section wrap">
    <blockquote class="quote">«Сначала выбираешь положения тегов, потом получаешь правила, по которым собираются все экраны.»
      <footer>— design.md</footer>
    </blockquote>
  </section>`
}

function cta(t) {
  return `<section class="cta-band"><div class="wrap">
    <h2>${t.mood.headline.replace(/\n/g, ' ')}</h2>
    <p class="lead" style="margin:0 auto 22px">${t.voice.lead}</p>
    <span class="btn btn-primary">${t.voice.cta}</span>
  </div></section>`
}

function footer(t) {
  return `<footer class="site-footer wrap"><span>${t.brand}</span><span>© дизайн-система</span></footer>`
}

function pricing() {
  const plans = [
    ['Start', '0', false],
    ['Studio', '29', true],
    ['Maison', '90', false],
  ]
  return `<section class="section wrap">
    <h2 class="section-title">Тарифы</h2>
    <div class="pricing">${plans
      .map(
        ([n, p, f]) => `<article class="card price-card ${f ? 'featured' : ''}">
        <h3>${n}</h3>
        <p class="price">${p} / мес</p>
        <p class="muted">Токены те же. Меняется только плотность секций.</p>
      </article>`,
      )
      .join('')}</div>
  </section>`
}

function products() {
  const items = [
    ['Объект 01', '12 400 ₽'],
    ['Объект 02', '8 900 ₽'],
    ['Объект 03', '16 200 ₽'],
  ]
  return `<section class="section wrap">
    <h2 class="section-title">Витрина</h2>
    <div class="grid-products">${items
      .map(
        ([n, p]) => `<article class="product">
        <div class="thumb"></div>
        <h3>${n}</h3>
        <p>Карточка собрана из surface, radius и линии палитры.</p>
        <div class="price">${p}</div>
      </article>`,
      )
      .join('')}</div>
  </section>`
}

function promo(t) {
  return `<section class="cta-band"><div class="wrap">
    <h2>Сезонная серия</h2>
    <p class="lead" style="margin:0 auto 22px">Одна промо-лента, без нового цвета.</p>
    <span class="btn btn-primary">${t.voice.cta}</span>
  </div></section>`
}

function masthead(t) {
  return `<div class="masthead wrap">
    <div class="nav-brand">${t.brand}</div>
    <div class="nav-links" style="justify-content:center;margin-top:12px">
      <span>Редакція</span><span>Город</span><span>Форма</span><span>Архив</span>
    </div>
  </div>`
}

function featured(t) {
  return `<section class="section wrap featured">
    <p class="kicker">${t.mood.kicker}</p>
    <h2>${t.mood.headline.replace(/\n/g, ' ')}</h2>
    <p class="lead">${t.voice.lead}</p>
  </section>`
}

function newsGrid() {
  const items = ['Поля и ритм', 'Пара шрифтов', 'Тёмная латунь', 'Сетка 12']
  return `<section class="section wrap">
    <div class="news-grid">${items
      .map((n) => `<article class="card"><h3>${n}</h3><p>Колонка из токенов, без отдельного стиля карточки.</p></article>`)
      .join('')}</div>
  </section>`
}

function intro(t) {
  return `<section class="hero is-centered wrap">
    <div>
      <p class="kicker">${t.mood.kicker}</p>
      <h1>${t.mood.headline}</h1>
      <p class="lead">${t.voice.lead}</p>
    </div>
  </section>`
}

function projects() {
  const items = [
    ['01', 'Система для дома', '2026'],
    ['02', 'Журнал сезона', '2025'],
    ['03', 'Витрина объектов', '2025'],
  ]
  return `<section class="wrap">${items
    .map(
      ([i, n, y]) => `<article class="project"><span>${i}</span><b>${n}</b><span>${y}</span></article>`,
    )
    .join('')}</section>`
}

const BLOCKS = {
  nav: (t) => nav(t, ['Работы', 'Метод', 'Контакт']),
  hero,
  logos,
  features,
  quote,
  cta,
  footer,
  pricing,
  products,
  promo,
  masthead,
  featured,
  grid: newsGrid,
  intro,
  projects,
}

function pageHTML(t) {
  return t.blocks.map((id) => BLOCKS[id]?.(t) || '').join('')
}

function tagClass(axisId, tagId) {
  return [
    'tag',
    isLocked(locked, axisId, tagId) ? 'is-locked' : '',
    isInferred(locked, axisId, tagId) ? 'is-inferred' : '',
    hover?.axis === axisId && hover.id === tagId ? 'is-hover' : '',
  ]
    .filter(Boolean)
    .join(' ')
}

function renderTree(t) {
  const sel = t.sel
  treeEl.innerHTML = GROUPS.map((group) => {
    const axes = group.axes
      .map((axis) => {
        const lockedHere = Boolean(locked[axis.id])
        const value = tagLabel(axis.id, sel[axis.id])
        const tags = axis.tags
          .map(
            (tag) =>
              `<button type="button" class="${tagClass(axis.id, tag.id)}" data-axis="${axis.id}" data-id="${tag.id}" title="${tag.hint}">${tag.label}</button>`,
          )
          .join('')
        return `<div class="axis ${lockedHere ? 'has-lock' : ''}" data-axis="${axis.id}">
          <span class="axis-dot"></span>
          <div class="axis-label"><span>${axis.label}</span><span class="axis-value">${value}</span></div>
          <div class="tags">${tags}</div>
        </div>`
      })
      .join('')
    return `<section class="tree-group"><h2 class="tree-group-title">${group.label}</h2>${axes}</section>`
  }).join('')
}

function syncTree(t) {
  for (const tag of treeEl.querySelectorAll('.tag')) {
    tag.className = tagClass(tag.dataset.axis, tag.dataset.id)
  }
  for (const axis of treeEl.querySelectorAll('.axis')) {
    const id = axis.dataset.axis
    axis.classList.toggle('has-lock', Boolean(locked[id]))
    const value = axis.querySelector('.axis-value')
    if (value) value.textContent = tagLabel(id, t.sel[id])
  }
}

function fitPreview() {
  const stage = viewportEl.querySelector('.preview-stage')
  const width = stage.clientWidth
  const scale = Math.min(1, width / 1200)
  browserEl.style.transform = `scale(${scale})`
  browserEl.style.marginBottom = `${(scale - 1) * browserEl.offsetHeight}px`
}

function render(rebuildTree = false) {
  const t = current()
  applyVars(pageEl, t)
  applyVars(browserEl, t)
  pageEl.innerHTML = pageHTML(t)
  mdEl.textContent = generateMarkdown(t)
  nameEl.textContent = t.name
  urlEl.textContent = `${t.brand.toLowerCase()}.site`
  hoverEl.textContent = t.hover
    ? `предпросмотр: ${tagLabel(t.hover.axis, t.hover.id)}`
    : 'наведите тег, чтобы примерять'
  if (rebuildTree || !treeEl.childElementCount) renderTree(t)
  else syncTree(t)
  requestAnimationFrame(fitPreview)
}

function commitHash() {
  const next = `#${serialize(locked)}`
  if (location.hash !== next) history.replaceState(null, '', next)
}

treeEl.addEventListener('pointerover', (e) => {
  const tag = e.target.closest('.tag')
  if (!tag) return
  const next = { axis: tag.dataset.axis, id: tag.dataset.id }
  if (hover?.axis === next.axis && hover.id === next.id) return
  hover = next
  render(false)
})

document.querySelector('[data-tree]').addEventListener('pointerleave', () => {
  if (!hover) return
  hover = null
  render(false)
})

treeEl.addEventListener('click', (e) => {
  const tag = e.target.closest('.tag')
  if (!tag) return
  const { axis, id } = tag.dataset
  if (axis === 'site' || axis === 'mood') {
    locked[axis] = id
  } else if (locked[axis] === id) {
    delete locked[axis]
  } else {
    locked[axis] = id
  }
  hover = { axis, id }
  commitHash()
  render(true)
})

document.querySelector('#btn-random').addEventListener('click', () => {
  locked = randomSelection()
  hover = null
  commitHash()
  render(true)
})

document.querySelector('#btn-reset').addEventListener('click', () => {
  locked = { ...DEFAULT_SELECTION }
  hover = null
  commitHash()
  render(true)
})

document.querySelector('#btn-copy').addEventListener('click', async () => {
  const text = generateMarkdown(current())
  try {
    await navigator.clipboard.writeText(text)
    document.querySelector('#btn-copy').textContent = 'Скопировано'
    setTimeout(() => {
      document.querySelector('#btn-copy').textContent = 'Копировать'
    }, 1400)
  } catch {
    document.querySelector('#btn-copy').textContent = 'Не удалось'
  }
})

document.querySelector('#btn-download').addEventListener('click', () => {
  const t = current()
  const blob = new Blob([generateMarkdown(t)], { type: 'text/markdown;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = 'design.md'
  a.click()
  URL.revokeObjectURL(a.href)
})

window.addEventListener('resize', fitPreview)
window.addEventListener('hashchange', () => {
  locked = parseHash(location.hash)
  hover = null
  render(true)
})

render(true)
