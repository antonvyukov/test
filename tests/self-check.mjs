import assert from 'node:assert/strict'
import {
  GROUPS,
  MOODS,
  PALETTES,
  TYPES,
  DEFAULT_SELECTION,
  resolve,
  generateMarkdown,
  serialize,
  parseHash,
  effectiveSelection,
  inferredFromMood,
  cssVars,
  axisIds,
  isInferred,
  isLocked,
} from '../js/core.js'

function check(name, ok) {
  assert.ok(ok, name)
  console.log('ok', name)
}

const tagSets = Object.fromEntries(
  GROUPS.flatMap((g) => g.axes).map((a) => [a.id, new Set(a.tags.map((t) => t.id))]),
)

check('all axes unique', new Set(axisIds()).size === axisIds().length)

for (const [moodId, mood] of Object.entries(MOODS)) {
  check(`mood ${moodId} exists as tag`, tagSets.mood.has(moodId))
  for (const key of axisIds()) {
    if (key === 'mood' || key === 'site' || !mood[key]) continue
    check(`mood ${moodId}.${key}=${mood[key]} is a real tag`, tagSets[key].has(mood[key]))
  }
}

for (const id of tagSets.palette) {
  check(`palette ${id} defined`, Boolean(PALETTES[id]))
}
for (const id of tagSets.type) {
  check(`type ${id} defined`, Boolean(TYPES[id]))
}

const base = resolve(DEFAULT_SELECTION)
check('default palette is ink-gold', base.sel.palette === 'ink-gold')
check('default brand ATELIER', base.brand === 'ATELIER')
check('default blocks include hero', base.blocks.includes('hero'))

const hovered = resolve(DEFAULT_SELECTION, { axis: 'palette', id: 'terra' })
check('hover overrides palette', hovered.sel.palette === 'terra')
check('hover does not stick on locked', resolve(DEFAULT_SELECTION).sel.palette === 'ink-gold')

const lockedPalette = resolve({ ...DEFAULT_SELECTION, palette: 'neon' })
check('locked palette wins over mood', lockedPalette.sel.palette === 'neon')

const unlocked = effectiveSelection({ site: 'shop', mood: 'brutalist' })
check('shop + brutalist infers mono', unlocked.palette === 'mono')
check('shop stays shop', unlocked.site === 'shop')

const md = generateMarkdown(base)
check('markdown has title', md.includes('# Design System — ATELIER Noir'))
check('markdown has default hex', md.includes('#12110F'))
check('markdown has css vars', md.includes('--primary: #C4A574'))
check('markdown has prompt', md.includes('Сгенерируй одностраничный сайт'))
check('markdown lists landing blocks', md.includes('Hero:'))

const shopMd = generateMarkdown(resolve({ site: 'shop', mood: 'playful' }))
check('shop markdown mentions витрина', /витрин/i.test(shopMd))
check('playful infers pastel unless locked', resolve({ site: 'shop', mood: 'playful' }).sel.palette === 'pastel')

const brutal = resolve({ site: 'landing', mood: 'brutalist' })
check('brutalist radius 0', brutal.radius === '0px')
check('brutalist rules ban rounding', generateMarkdown(brutal).includes('Не скруглять углы'))

const hash = serialize({ site: 'saas', mood: 'techno', palette: 'ocean' })
const parsed = parseHash(hash)
check('serialize roundtrip site', parsed.site === 'saas')
check('serialize roundtrip mood', parsed.mood === 'techno')
check('serialize roundtrip palette', parsed.palette === 'ocean')
check('parse ignores junk tags', parseHash('mood=nope&site=landing').mood === 'editorial')

check('inferred dashed palette', isInferred(DEFAULT_SELECTION, 'palette', 'ink-gold'))
check('locked not inferred', isInferred({ ...DEFAULT_SELECTION, palette: 'paper' }, 'palette', 'paper') === false)
check('isLocked true', isLocked({ ...DEFAULT_SELECTION, type: 'syne' }, 'type', 'syne'))

const vars = cssVars(base)
check('css var bg', vars['--bg'] === '#12110F')
check('css var h1 expressive', vars['--h1'] === '84px')

console.log('\nall checks passed')
