/** Tag tree, token resolve, and design.md generator. Pure module — no DOM. */

export const DEFAULT_SELECTION = { site: 'landing', mood: 'editorial' }

export const GROUPS = [
  {
    id: 'essence',
    label: 'Суть',
    axes: [
      {
        id: 'site',
        label: 'Тип сайта',
        tags: [
          { id: 'landing', label: 'Лендинг', hint: 'Герой, выгоды, соцдоказательство, CTA' },
          { id: 'saas', label: 'SaaS', hint: 'Продукт, тарифы, логотипы клиентов' },
          { id: 'shop', label: 'Магазин', hint: 'Витрина, карточки товара, промо' },
          { id: 'media', label: 'Медиа', hint: 'Шапка издания, сетка материалов' },
          { id: 'portfolio', label: 'Портфолио', hint: 'Крупная типографика, проекты' },
        ],
      },
      {
        id: 'mood',
        label: 'Настроение',
        tags: [
          { id: 'minimal', label: 'Минимализм', hint: 'Воздух, мало цвета, тихая сетка' },
          { id: 'editorial', label: 'Эдиториал', hint: 'Журнальная вёрстка, крупный шрифт' },
          { id: 'brutalist', label: 'Брутализм', hint: 'Сырой HTML-жест, толстая линия' },
          { id: 'playful', label: 'Игривый', hint: 'Яркий акцент, мягкие формы' },
          { id: 'luxury', label: 'Люкс', hint: 'Тёмное поле, золото, сдержанность' },
          { id: 'organic', label: 'Органика', hint: 'Земля, мягкий радиус, живые фото' },
          { id: 'techno', label: 'Техно', hint: 'Тёмная геометрия, неон, моно' },
          { id: 'corporate', label: 'Корпоратив', hint: 'Доверие, спокойный синий, карточки' },
        ],
      },
      {
        id: 'voice',
        label: 'Голос',
        tags: [
          { id: 'calm', label: 'Сдержанный', hint: 'Короткие фразы, без восклицаний' },
          { id: 'bold', label: 'Дерзкий', hint: 'Резкие заголовки, прямое обращение' },
          { id: 'warm', label: 'Тёплый', hint: 'Дружелюбно, на «вы», живой ритм' },
          { id: 'expert', label: 'Экспертный', hint: 'Точность, термины, без сленга' },
          { id: 'poetic', label: 'Поэтичный', hint: 'Метафоры, длинное дыхание строк' },
        ],
      },
    ],
  },
  {
    id: 'color',
    label: 'Цвет',
    axes: [
      {
        id: 'palette',
        label: 'Палитра',
        tags: [
          { id: 'paper', label: 'Бумага', hint: 'Тёплый офф-уайт и чернила' },
          { id: 'ink-gold', label: 'Чернила и золото', hint: 'Тёмный люкс, латунный акцент' },
          { id: 'nordic', label: 'Нордик', hint: 'Холодный серо-синий' },
          { id: 'terra', label: 'Терракота', hint: 'Глина, песок, тёплый акцент' },
          { id: 'forest', label: 'Лес', hint: 'Зелень и охра' },
          { id: 'neon', label: 'Неон', hint: 'Ночь, мята и ультрамарин' },
          { id: 'pastel', label: 'Пастель', hint: 'Пудровая роза и мята' },
          { id: 'mono', label: 'Моно', hint: 'Чёрное и белое, высокий контраст' },
          { id: 'ocean', label: 'Океан', hint: 'Глубокий teal, песчаный акцент' },
          { id: 'wine', label: 'Вино', hint: 'Бордо, ночь, тёплое золото' },
          { id: 'sakura', label: 'Сакура', hint: 'Чернила на розовой бумаге' },
          { id: 'sunset', label: 'Закат', hint: 'Коралл и чернила' },
        ],
      },
    ],
  },
  {
    id: 'type',
    label: 'Шрифт',
    axes: [
      {
        id: 'type',
        label: 'Пара',
        tags: [
          { id: 'inter', label: 'Inter', hint: 'Нейтральный гротеск' },
          { id: 'syne', label: 'Syne / Manrope', hint: 'Геометрия заголовков' },
          { id: 'playfair', label: 'Playfair / Source Serif', hint: 'Классическая антиква' },
          { id: 'fraunces', label: 'Fraunces / Newsreader', hint: 'Мягкая антиква с контрастом' },
          { id: 'space', label: 'Space Grotesk', hint: 'Техно-гротеск' },
          { id: 'bebas', label: 'Bebas / Archivo', hint: 'Плакатный набор' },
          { id: 'cormorant', label: 'Cormorant / Karla', hint: 'Узкая антиква люкса' },
          { id: 'ibm', label: 'IBM Plex', hint: 'Продуктовый гротеск' },
          { id: 'outfit', label: 'Outfit', hint: 'Круглый геометрический' },
          { id: 'instrument', label: 'Instrument / DM Sans', hint: 'Редакционная пара' },
        ],
      },
      {
        id: 'scale',
        label: 'Масштаб',
        tags: [
          { id: 'modest', label: 'Скромный', hint: 'H1 около 48px, спокойный ритм' },
          { id: 'expressive', label: 'Выразительный', hint: 'H1 72–96px, журнальный удар' },
        ],
      },
    ],
  },
  {
    id: 'shape',
    label: 'Форма',
    axes: [
      {
        id: 'radius',
        label: 'Радиус',
        tags: [
          { id: 'none', label: 'Острый', hint: '0 — жёсткий угол' },
          { id: 'sm', label: 'Лёгкий', hint: '4px' },
          { id: 'md', label: 'Мягкий', hint: '12px' },
          { id: 'lg', label: 'Крупный', hint: '24px' },
          { id: 'full', label: 'Пилюля', hint: '999px на кнопках' },
        ],
      },
      {
        id: 'shadow',
        label: 'Тень',
        tags: [
          { id: 'none', label: 'Плоский', hint: 'Без тени, только плоскость' },
          { id: 'soft', label: 'Мягкая', hint: 'Размытое свечение' },
          { id: 'layered', label: 'Слои', hint: 'Карточка слегка приподнята' },
          { id: 'hard', label: 'Жёсткая', hint: 'Сдвиг без блюра' },
        ],
      },
      {
        id: 'border',
        label: 'Линия',
        tags: [
          { id: 'none', label: 'Нет', hint: 'Разделение только воздухом' },
          { id: 'hairline', label: 'Волосная', hint: '1px' },
          { id: 'thick', label: 'Толстая', hint: '2px, заметный контур' },
        ],
      },
      {
        id: 'button',
        label: 'Кнопка',
        tags: [
          { id: 'solid', label: 'Заливка', hint: 'Primary — сплошной' },
          { id: 'outline', label: 'Контур', hint: 'Primary — обводка' },
          { id: 'ghost', label: 'Призрак', hint: 'Текст + линия, минимум пятна' },
        ],
      },
    ],
  },
  {
    id: 'layout',
    label: 'Макет',
    axes: [
      {
        id: 'density',
        label: 'Плотность',
        tags: [
          { id: 'compact', label: 'Компакт', hint: 'Тесные поля, много контента' },
          { id: 'regular', label: 'Комфорт', hint: 'Обычный ритм лендинга' },
          { id: 'airy', label: 'Воздух', hint: 'Крупные отступы, мало блоков' },
        ],
      },
      {
        id: 'width',
        label: 'Ширина',
        tags: [
          { id: 'narrow', label: 'Узкая', hint: '≈920px, редакционная колонка' },
          { id: 'wide', label: 'Широкая', hint: '≈1200px' },
          { id: 'full', label: 'Полная', hint: '≈1440px, почти край' },
        ],
      },
      {
        id: 'hero',
        label: 'Герой',
        tags: [
          { id: 'centered', label: 'Центр', hint: 'Текст по центру, медиа ниже' },
          { id: 'split', label: 'Сплит', hint: 'Текст | медиа, 50/50' },
          { id: 'offset', label: 'Оффсет', hint: 'Асимметрия, сдвиг колонки' },
          { id: 'full', label: 'Полотно', hint: 'На весь экран, оверлей' },
        ],
      },
    ],
  },
  {
    id: 'atmosphere',
    label: 'Атмосфера',
    axes: [
      {
        id: 'motion',
        label: 'Движение',
        tags: [
          { id: 'none', label: 'Статика', hint: 'Без анимаций' },
          { id: 'subtle', label: 'Шёпот', hint: '200ms, только hover/фокус' },
          { id: 'expressive', label: 'Жест', hint: 'Заметный вход секций' },
        ],
      },
      {
        id: 'image',
        label: 'Картинка',
        tags: [
          { id: 'none', label: 'Типографика', hint: 'Без декоративных фото' },
          { id: 'photo', label: 'Фото', hint: 'Документальная съёмка' },
          { id: 'illustration', label: 'Иллюстрация', hint: 'Плоские пятна, фигуры' },
          { id: 'abstract', label: 'Абстракция', hint: 'Сетка, градиент, шум' },
        ],
      },
    ],
  },
]

export const PALETTES = {
  paper: {
    word: 'Paper',
    scheme: 'light',
    bg: '#F6F1E8',
    fg: '#1A1714',
    muted: '#6F675E',
    faint: '#A39A8E',
    primary: '#1A1714',
    primaryFg: '#F6F1E8',
    accent: '#B85C38',
    surface: '#FFFCF7',
    surface2: '#EFE8DC',
    border: '#E4DCD0',
  },
  'ink-gold': {
    word: 'Noir',
    scheme: 'dark',
    bg: '#12110F',
    fg: '#F3EDE2',
    muted: '#A39886',
    faint: '#6F6758',
    primary: '#C4A574',
    primaryFg: '#16140F',
    accent: '#E8D5B5',
    surface: '#1C1A17',
    surface2: '#26231E',
    border: '#2E2B26',
  },
  nordic: {
    word: 'Nord',
    scheme: 'light',
    bg: '#F3F6F8',
    fg: '#1C2430',
    muted: '#5C6B7A',
    faint: '#93A0AD',
    primary: '#2F5D7C',
    primaryFg: '#F3F6F8',
    accent: '#C45D3A',
    surface: '#FFFFFF',
    surface2: '#E7EEF2',
    border: '#D5DDE3',
  },
  terra: {
    word: 'Clay',
    scheme: 'light',
    bg: '#FBF6F1',
    fg: '#2A1E16',
    muted: '#7A6556',
    faint: '#B39A86',
    primary: '#C45D3A',
    primaryFg: '#FFF8F3',
    accent: '#2A1E16',
    surface: '#FFFFFF',
    surface2: '#F0E4D8',
    border: '#E8D8C8',
  },
  forest: {
    word: 'Fern',
    scheme: 'light',
    bg: '#F3F1EB',
    fg: '#1C241C',
    muted: '#5E6B5A',
    faint: '#94A08E',
    primary: '#3F6B4F',
    primaryFg: '#F4F7F2',
    accent: '#C17B3A',
    surface: '#FFFEF8',
    surface2: '#E4E6D8',
    border: '#D7D3C6',
  },
  neon: {
    word: 'Pulse',
    scheme: 'dark',
    bg: '#07080D',
    fg: '#E8F0FF',
    muted: '#7E8AA6',
    faint: '#4E5870',
    primary: '#3DFFB0',
    primaryFg: '#062016',
    accent: '#6B7CFF',
    surface: '#10121A',
    surface2: '#181C28',
    border: '#242836',
  },
  pastel: {
    word: 'Bloom',
    scheme: 'light',
    bg: '#FFF8F4',
    fg: '#3D2C3A',
    muted: '#8A7080',
    faint: '#C4A8B4',
    primary: '#E07A9A',
    primaryFg: '#FFF7FA',
    accent: '#3D8A9A',
    surface: '#FFFFFF',
    surface2: '#F8E8EE',
    border: '#F0DDE4',
  },
  mono: {
    word: 'Ink',
    scheme: 'light',
    bg: '#FAFAFA',
    fg: '#111111',
    muted: '#5C5C5C',
    faint: '#9A9A9A',
    primary: '#111111',
    primaryFg: '#FAFAFA',
    accent: '#111111',
    surface: '#FFFFFF',
    surface2: '#EFEFEF',
    border: '#111111',
  },
  ocean: {
    word: 'Tide',
    scheme: 'dark',
    bg: '#0B1C24',
    fg: '#E6F2F5',
    muted: '#7FA3AD',
    faint: '#4D6E77',
    primary: '#3CA6B8',
    primaryFg: '#062026',
    accent: '#E8B86D',
    surface: '#12262F',
    surface2: '#1A3340',
    border: '#1E3A45',
  },
  wine: {
    word: 'Vin',
    scheme: 'dark',
    bg: '#1A1014',
    fg: '#F6E8EA',
    muted: '#B08A90',
    faint: '#7A5A60',
    primary: '#C44A62',
    primaryFg: '#1A1014',
    accent: '#D4A574',
    surface: '#24161C',
    surface2: '#301E26',
    border: '#3A242C',
  },
  sakura: {
    word: 'Hana',
    scheme: 'light',
    bg: '#FFF7F5',
    fg: '#2B1A1F',
    muted: '#8C6A72',
    faint: '#C4A0A8',
    primary: '#C45C6A',
    primaryFg: '#FFF7F5',
    accent: '#2B1A1F',
    surface: '#FFFFFF',
    surface2: '#F8E4E6',
    border: '#F0D8DC',
  },
  sunset: {
    word: 'Ember',
    scheme: 'light',
    bg: '#FFF6EE',
    fg: '#2C1810',
    muted: '#8B6454',
    faint: '#C4A090',
    primary: '#E25B2A',
    primaryFg: '#FFF8F3',
    accent: '#1C3A4A',
    surface: '#FFFFFF',
    surface2: '#F5E0D0',
    border: '#F0D8C4',
  },
}

export const TYPES = {
  inter: { label: 'Inter', display: 'Inter', body: 'Inter', mono: 'IBM Plex Mono' },
  syne: { label: 'Syne / Manrope', display: 'Syne', body: 'Manrope', mono: 'IBM Plex Mono' },
  playfair: { label: 'Playfair / Source Serif', display: 'Playfair Display', body: 'Source Serif 4', mono: 'IBM Plex Mono' },
  fraunces: { label: 'Fraunces / Newsreader', display: 'Fraunces', body: 'Newsreader', mono: 'IBM Plex Mono' },
  space: { label: 'Space Grotesk', display: 'Space Grotesk', body: 'Inter', mono: 'IBM Plex Mono' },
  bebas: { label: 'Bebas / Archivo', display: 'Bebas Neue', body: 'Archivo', mono: 'IBM Plex Mono' },
  cormorant: { label: 'Cormorant / Karla', display: 'Cormorant Garamond', body: 'Karla', mono: 'IBM Plex Mono' },
  ibm: { label: 'IBM Plex', display: 'IBM Plex Sans', body: 'IBM Plex Sans', mono: 'IBM Plex Mono' },
  outfit: { label: 'Outfit', display: 'Outfit', body: 'Outfit', mono: 'IBM Plex Mono' },
  instrument: { label: 'Instrument / DM Sans', display: 'Instrument Serif', body: 'DM Sans', mono: 'IBM Plex Mono' },
}

export const RADII = { none: '0px', sm: '4px', md: '12px', lg: '24px', full: '999px' }
export const BORDERS = { none: '0px', hairline: '1px', thick: '2px' }
export const WIDTHS = { narrow: 920, wide: 1200, full: 1440 }
export const DENSITY = { compact: 0.72, regular: 1, airy: 1.38 }
export const SCALE = { modest: 48, expressive: 84 }

export const SHADOWS = {
  none: 'none',
  soft: '0 18px 50px color-mix(in srgb, var(--fg) 14%, transparent)',
  layered: '0 1px 2px color-mix(in srgb, var(--fg) 6%, transparent), 0 16px 32px color-mix(in srgb, var(--fg) 8%, transparent)',
  hard: '6px 6px 0 var(--fg)',
}

export const MOODS = {
  minimal: {
    brand: 'LINE',
    kicker: 'Пространство',
    headline: 'Меньше форм.\nБольше смысла.',
    palette: 'paper',
    type: 'inter',
    radius: 'sm',
    shadow: 'none',
    density: 'airy',
    hero: 'centered',
    motion: 'none',
    image: 'none',
    button: 'outline',
    scale: 'modest',
    border: 'hairline',
    width: 'narrow',
    voice: 'calm',
  },
  editorial: {
    brand: 'ATELIER',
    kicker: 'Весна / номер 04',
    headline: 'Страница как\nразворот журнала.',
    palette: 'ink-gold',
    type: 'playfair',
    radius: 'none',
    shadow: 'none',
    density: 'airy',
    hero: 'offset',
    motion: 'subtle',
    image: 'photo',
    button: 'solid',
    scale: 'expressive',
    border: 'hairline',
    width: 'wide',
    voice: 'poetic',
  },
  brutalist: {
    brand: 'RAW',
    kicker: 'NO THEME',
    headline: 'СНАЧАЛА СТРУКТУРА.\nПОТОМ УКРАШЕНИЯ.',
    palette: 'mono',
    type: 'bebas',
    radius: 'none',
    shadow: 'hard',
    density: 'compact',
    hero: 'full',
    motion: 'none',
    image: 'none',
    button: 'solid',
    scale: 'expressive',
    border: 'thick',
    width: 'full',
    voice: 'bold',
  },
  playful: {
    brand: 'BOOP',
    kicker: 'Привет',
    headline: 'Сделано с удовольствием,\nне с шаблоном.',
    palette: 'pastel',
    type: 'syne',
    radius: 'full',
    shadow: 'soft',
    density: 'regular',
    hero: 'centered',
    motion: 'expressive',
    image: 'illustration',
    button: 'solid',
    scale: 'expressive',
    border: 'none',
    width: 'wide',
    voice: 'warm',
  },
  luxury: {
    brand: 'NOIR',
    kicker: 'Maison',
    headline: 'Тишина\nкак материал.',
    palette: 'ink-gold',
    type: 'cormorant',
    radius: 'sm',
    shadow: 'soft',
    density: 'airy',
    hero: 'split',
    motion: 'subtle',
    image: 'photo',
    button: 'ghost',
    scale: 'expressive',
    border: 'hairline',
    width: 'narrow',
    voice: 'calm',
  },
  organic: {
    brand: 'FERN',
    kicker: 'С земли',
    headline: 'Форма следует\nза материалом.',
    palette: 'forest',
    type: 'fraunces',
    radius: 'lg',
    shadow: 'soft',
    density: 'regular',
    hero: 'split',
    motion: 'subtle',
    image: 'photo',
    button: 'solid',
    scale: 'modest',
    border: 'hairline',
    width: 'wide',
    voice: 'warm',
  },
  techno: {
    brand: 'PULSE',
    kicker: 'v2.4 / live',
    headline: 'Сигнал чистый.\nИнтерфейс — нет.',
    palette: 'neon',
    type: 'space',
    radius: 'none',
    shadow: 'none',
    density: 'compact',
    hero: 'full',
    motion: 'expressive',
    image: 'abstract',
    button: 'solid',
    scale: 'modest',
    border: 'hairline',
    width: 'wide',
    voice: 'expert',
  },
  corporate: {
    brand: 'NORTH',
    kicker: 'Платформа',
    headline: 'Ясность, на которой\nдержатся решения.',
    palette: 'nordic',
    type: 'ibm',
    radius: 'md',
    shadow: 'layered',
    density: 'regular',
    hero: 'split',
    motion: 'subtle',
    image: 'photo',
    button: 'solid',
    scale: 'modest',
    border: 'hairline',
    width: 'wide',
    voice: 'expert',
  },
}

export const SITE_BLOCKS = {
  landing: ['nav', 'hero', 'logos', 'features', 'quote', 'cta', 'footer'],
  saas: ['nav', 'hero', 'logos', 'features', 'pricing', 'cta', 'footer'],
  shop: ['nav', 'hero', 'products', 'promo', 'quote', 'footer'],
  media: ['nav', 'masthead', 'featured', 'grid', 'footer'],
  portfolio: ['nav', 'intro', 'projects', 'quote', 'footer'],
}

export const VOICE_COPY = {
  calm: { cta: 'Смотреть коллекцию', secondary: 'О методе', lead: 'Спокойный ритм, точные поля, ничего лишнего на первом экране.' },
  bold: { cta: 'Запустить', secondary: 'Без демо', lead: 'Режем шум. Один экран — одно действие. Остальное не показываем.' },
  warm: { cta: 'Пойдёмте', secondary: 'Рассказать', lead: 'Делаем страницы, на которых приятно остаться и легко понять, что дальше.' },
  expert: { cta: 'Документация', secondary: 'Архитектура', lead: 'Токены, сетка и правила компонентов — чтобы страницы собирались без импровизации.' },
  poetic: { cta: 'Читать дальше', secondary: 'Выпуск', lead: 'Каждый блок — как колонка в журнале: воздух, удар заголовка, тихий текст.' },
}

const AXIS_INDEX = (() => {
  const axes = {}
  const tags = {}
  for (const group of GROUPS) {
    for (const axis of group.axes) {
      axes[axis.id] = axis
      tags[axis.id] = Object.fromEntries(axis.tags.map((t) => [t.id, t]))
    }
  }
  return { axes, tags }
})()

export function axisIds() {
  return Object.keys(AXIS_INDEX.axes)
}

export function tagLabel(axisId, tagId) {
  return AXIS_INDEX.tags[axisId]?.[tagId]?.label ?? tagId
}

export function inferredFromMood(moodId) {
  const mood = MOODS[moodId] || MOODS.editorial
  const next = {}
  for (const key of axisIds()) {
    if (key === 'mood' || key === 'site') continue
    if (mood[key]) next[key] = mood[key]
  }
  return next
}

/**
 * Locked selection + optional hover overlay.
 * Unlocked axes follow the active mood.
 */
export function effectiveSelection(locked, hover = null) {
  const moodId = hover?.axis === 'mood' ? hover.id : locked.mood || DEFAULT_SELECTION.mood
  const inferred = inferredFromMood(moodId)
  const merged = { ...inferred, ...locked }
  if (hover?.axis && hover.id) merged[hover.axis] = hover.id
  if (!merged.site) merged.site = DEFAULT_SELECTION.site
  if (!merged.mood) merged.mood = DEFAULT_SELECTION.mood
  return merged
}

export function resolve(locked, hover = null) {
  const sel = effectiveSelection(locked, hover)
  const mood = MOODS[sel.mood] || MOODS.editorial
  const colors = PALETTES[sel.palette] || PALETTES.paper
  const fonts = TYPES[sel.type] || TYPES.inter
  const radius = RADII[sel.radius] ?? RADII.sm
  const shadow = SHADOWS[sel.shadow] ?? SHADOWS.none
  const border = BORDERS[sel.border] ?? BORDERS.hairline
  const density = DENSITY[sel.density] ?? 1
  const maxw = WIDTHS[sel.width] ?? 1200
  const h1 = SCALE[sel.scale] ?? 48
  const voice = VOICE_COPY[sel.voice] || VOICE_COPY.calm
  const btnRadius = sel.radius === 'full' ? '999px' : radius
  const cardRadius = sel.radius === 'full' ? RADII.lg : radius

  return {
    sel,
    locked,
    hover,
    mood,
    colors,
    fonts,
    radius,
    btnRadius,
    cardRadius,
    shadow,
    border,
    density,
    maxw,
    h1,
    voice,
    brand: mood.brand,
    name: `${mood.brand} ${colors.word}`,
    blocks: SITE_BLOCKS[sel.site] || SITE_BLOCKS.landing,
    inferred: inferredFromMood(sel.mood),
  }
}

export function isInferred(locked, axisId, tagId) {
  if (locked[axisId]) return false
  if (axisId === 'site' || axisId === 'mood') return false
  const moodId = locked.mood || DEFAULT_SELECTION.mood
  return inferredFromMood(moodId)[axisId] === tagId
}

export function isLocked(locked, axisId, tagId) {
  return locked[axisId] === tagId
}

export function serialize(locked) {
  const params = new URLSearchParams()
  for (const key of axisIds()) {
    if (locked[key]) params.set(key, locked[key])
  }
  return params.toString()
}

export function parseHash(hash) {
  const raw = String(hash || '').replace(/^#/, '')
  const params = new URLSearchParams(raw)
  const locked = {}
  for (const key of axisIds()) {
    const value = params.get(key)
    if (value && AXIS_INDEX.tags[key]?.[value]) locked[key] = value
  }
  if (!locked.site) locked.site = DEFAULT_SELECTION.site
  if (!locked.mood) locked.mood = DEFAULT_SELECTION.mood
  return locked
}

export function randomSelection() {
  const locked = {}
  for (const group of GROUPS) {
    for (const axis of group.axes) {
      const i = Math.floor(Math.random() * axis.tags.length)
      locked[axis.id] = axis.tags[i].id
    }
  }
  return locked
}

function rules(t) {
  const s = t.sel
  const doList = [
    'Использовать только токены из этого файла. Новые цвета и шрифты не вводить.',
    `Держать максимальную ширину контента ${t.maxw}px, фон страницы — ${t.colors.bg}.`,
    `Заголовки — ${t.fonts.display}, текст — ${t.fonts.body}, код/метки — ${t.fonts.mono}.`,
    `Кнопки primary: стиль «${tagLabel('button', s.button)}», радиус ${t.btnRadius}.`,
  ]
  const dont = [
    'Не добавлять тени, градиенты и обводки, которых нет в токенах.',
    'Не смешивать больше одной акцентной кнопки в одном экране.',
    'Не выравнивать декор «на глаз»: только сетка и ритм из раздела «Макет».',
  ]

  if (s.mood === 'brutalist') {
    doList.push('Толстые линии, плакатный набор, резкие стыки блоков.')
    dont.push('Не скруглять углы, не использовать стоковые «улыбчивые» фото.')
  }
  if (s.mood === 'luxury' || s.mood === 'editorial') {
    doList.push('Давать заголовку воздух сверху и снизу. Мало элементов в герое.')
    dont.push('Не ставить яркие бейджи, не использовать UI-иллюстрации из библиотек.')
  }
  if (s.mood === 'minimal') {
    doList.push('Пустое поле — часть композиции. Один акцент на экран.')
    dont.push('Не заполнять секции карточками «чтобы не было пусто».')
  }
  if (s.mood === 'playful') {
    doList.push('Крупные радиусы, живой микрокопирайт, пятно акцента на CTA.')
    dont.push('Не делать корпоративный серый и мелкий текст в герое.')
  }
  if (s.mood === 'techno') {
    doList.push('Тёмные поверхности, моноширинные метки, геометрические подложки.')
    dont.push('Не использовать антикву и пастельные фото людей.')
  }
  if (s.image === 'none') {
    dont.push('Не вставлять декоративные фото и стоковые портреты.')
    doList.push('Строить блоки на типографике, линиях и цветовых плашках.')
  }
  if (s.motion === 'none') {
    dont.push('Не добавлять анимации появления, параллакс и автокарусели.')
  }
  if (s.density === 'airy') {
    doList.push('Вертикальный ритм щедрый: секции не прижимаются друг к другу.')
  }
  if (s.density === 'compact') {
    doList.push('Поля сжаты, информация плотная, герой не занимает весь viewport зря.')
  }
  if (t.colors.scheme === 'dark') {
    doList.push('Не использовать чистый #000 фоном. Тени — цветные, не чёрные дыры.')
  }

  return { do: doList, dont }
}

function siteSpec(site) {
  const specs = {
    landing: {
      title: 'Лендинг',
      blocks: [
        'Nav: логотип слева, 3–5 якорей, одна CTA справа.',
        'Hero: оффер + лид + две кнопки. Медиа только если токен картинки не «типографика».',
        'Логотипы: ряд из 4–6 текстовых марок, без цветных PNG.',
        'Выгоды: 3 колонки, заголовок + 2 предложения, без иконок-клипарта.',
        'Цитата: одна, крупно, с именем.',
        'CTA-лента: повтор оффера, одна кнопка.',
        'Footer: логотип, набор ссылок, копирайт.',
      ],
    },
    saas: {
      title: 'SaaS',
      blocks: [
        'Nav + Hero с продуктовым оффером и CTA «Документация» / «Запустить».',
        'Логотипы клиентов — текстовые, монохром.',
        'Возможности: 3 карточки на сетке токенов.',
        'Тарифы: 3 колонки, средняя может быть акцентной поверхностью, не новым цветом.',
        'CTA и footer.',
      ],
    },
    shop: {
      title: 'Магазин',
      blocks: [
        'Nav с каталогом и кнопкой корзины (иконка — линия, не картинка).',
        'Hero коллекции + CTA на витрину.',
        'Сетка товаров 3×n: фото-плашка, название, цена, вторичная кнопка.',
        'Промо-лента одним пятном primary/accent.',
        'Отзыв и footer.',
      ],
    },
    media: {
      title: 'Медиа',
      blocks: [
        'Мачта: название издания, рубрики одной строкой, без «megamenu».',
        'Главный материал — крупный заголовок + лид, как в газете.',
        'Сетка: 1 featured + лента из 4 карточек одной высоты.',
        'Footer с рубриками.',
      ],
    },
    portfolio: {
      title: 'Портфолио',
      blocks: [
        'Nav минимальный: имя / работы / связь.',
        'Intro: крупный display-заголовок на 2–3 строки.',
        'Проекты: 2 колонки, индекс + название + год, без лишних тегов.',
        'Короткий отзыв, footer с контактом.',
      ],
    },
  }
  return specs[site] || specs.landing
}

export function generateMarkdown(t) {
  const s = t.sel
  const c = t.colors
  const { do: dos, dont } = rules(t)
  const site = siteSpec(s.site)
  const lockedLine = axisIds()
    .filter((id) => t.locked[id])
    .map((id) => `${id}: ${t.locked[id]}`)
    .join(', ')

  const tokenTable = [
    ['bg', c.bg, 'Фон страницы'],
    ['fg', c.fg, 'Основной текст'],
    ['muted', c.muted, 'Вторичный текст'],
    ['faint', c.faint, 'Подписи, мета'],
    ['primary', c.primary, 'CTA, ключевой акцент'],
    ['primary-fg', c.primaryFg, 'Текст на primary'],
    ['accent', c.accent, 'Редкий акцент, не кнопки пачками'],
    ['surface', c.surface, 'Карточки, панели'],
    ['surface-2', c.surface2, 'Плашки, логотипы'],
    ['border', c.border, 'Разделители'],
  ]

  return `# Design System — ${t.name}

> Источник правды для генерации страниц. Не отступать от токенов и правил.
> Настроение: **${tagLabel('mood', s.mood)}**. Тип: **${tagLabel('site', s.site)}**. Голос: **${tagLabel('voice', s.voice)}**.

## Характер

- Бренд в превью: **${t.brand}**
- Крикер: «${t.mood.kicker}»
- Заголовок-ориентир: «${t.mood.headline.replace(/\n/g, ' / ')}»
- Голос интерфейса: ${tagLabel('voice', s.voice)}. CTA: «${t.voice.cta}». Вторичная: «${t.voice.secondary}».
- Картинка: ${tagLabel('image', s.image)}. Движение: ${tagLabel('motion', s.motion)}.

## Цвет

Режим: **${c.scheme === 'dark' ? 'тёмный' : 'светлый'}**. Палитра: **${tagLabel('palette', s.palette)}**.

| Токен | Значение | Где |
| --- | --- | --- |
${tokenTable.map((row) => `| \`--${row[0]}\` | \`${row[1]}\` | ${row[2]} |`).join('\n')}

\`\`\`css
:root {
  --bg: ${c.bg};
  --fg: ${c.fg};
  --muted: ${c.muted};
  --faint: ${c.faint};
  --primary: ${c.primary};
  --primary-fg: ${c.primaryFg};
  --accent: ${c.accent};
  --surface: ${c.surface};
  --surface-2: ${c.surface2};
  --border: ${c.border};
}
\`\`\`

## Типографика

- Display / H1–H2: **${t.fonts.display}**
- Body / UI: **${t.fonts.body}**
- Mono / кикер, цена, код: **${t.fonts.mono}**
- Масштаб: **${tagLabel('scale', s.scale)}** — H1 ≈ ${t.h1}px, H2 ≈ ${Math.round(t.h1 * 0.48)}px, body 16–18px, line-height 1.55–1.7
- Tracking заголовков: ${s.mood === 'luxury' || s.mood === 'editorial' ? 'чуть сжатый (−0.02em)' : s.mood === 'brutalist' ? 'широкий на кикере (+0.12em)' : 'нормальный'}
- Заголовки не центрировать, если герой не «центр».

## Макет

- Max-width: **${t.maxw}px**, колонки: 12, gutter: ${Math.round(24 * t.density)}px
- Плотность: **${tagLabel('density', s.density)}** (множитель ритма ${t.density})
- Секции: padding-block ≈ ${Math.round(72 * t.density)}px
- Герой: **${tagLabel('hero', s.hero)}**
- Радиус карточек: ${t.cardRadius}; кнопок: ${t.btnRadius}
- Тень: ${tagLabel('shadow', s.shadow)} → \`${t.shadow}\`
- Линия: ${tagLabel('border', s.border)} (${t.border} solid var(--border))

## Компоненты

### Кнопка
- Primary: ${tagLabel('button', s.button)}, фон/линия из \`--primary\`, текст \`--primary-fg\` (для заливки) или \`--primary\` (для контура).
- Secondary: всегда тише primary (призрак или волосная линия).
- Высота 44–48px, горизонтальный padding 20–28px, без градиента.

### Карточка
- Фон \`--surface\`, радиус ${t.cardRadius}, рамка ${t.border} \`--border\`${s.shadow === 'none' ? '' : ', тень из токена'}.
- Внутри: заголовок + 1 абзац. Не больше одной CTA на карточку.

### Навигация
- Высота ${s.density === 'compact' ? '56px' : '72px'}, фон страницы, нижняя линия только если токен линии не «нет».
- Ссылки цветом \`--muted\`, hover \`--fg\`. Никакого цветного логотипа.

### Поле ввода (если понадобится форма)
- Та же линия и радиус, что у кнопок-контуров. Фокус: 2px \`--primary\`, без glow-радуги.

## Страница: ${site.title}

Блоки в этом порядке:

${site.blocks.map((b, i) => `${i + 1}. ${b}`).join('\n')}

Превью-блоки, которые обязательны: ${t.blocks.join(', ')}.

## Правила генерации

### Делать
${dos.map((x) => `- ${x}`).join('\n')}

### Не делать
${dont.map((x) => `- ${x}`).join('\n')}

## Промпт

Сгенерируй одностраничный сайт типа «${tagLabel('site', s.site)}» **строго по этой дизайн-системе**.
Собери перечисленные блоки, не добавляй новых секций.
Скопируй токены в CSS-переменные и используй только их.
Тексты — на русском, голос «${tagLabel('voice', s.voice)}».
Бренд: ${t.brand}. Не придумывай другой визуальный стиль.

---
_locked: ${lockedLine || '(только настроение и тип — остальное из настроения)'}_
`
}

export function cssVars(t) {
  const c = t.colors
  return {
    '--bg': c.bg,
    '--fg': c.fg,
    '--muted': c.muted,
    '--faint': c.faint,
    '--primary': c.primary,
    '--primary-fg': c.primaryFg,
    '--accent': c.accent,
    '--surface': c.surface,
    '--surface-2': c.surface2,
    '--border': c.border,
    '--radius': t.cardRadius,
    '--radius-btn': t.btnRadius,
    '--shadow': t.shadow,
    '--stroke': t.border,
    '--maxw': `${t.maxw}px`,
    '--h1': `${t.h1}px`,
    '--pad': `${Math.round(72 * t.density)}px`,
    '--gap': `${Math.round(24 * t.density)}px`,
    '--font-display': `'${t.fonts.display}', serif`,
    '--font-body': `'${t.fonts.body}', system-ui, sans-serif`,
    '--font-mono': `'${t.fonts.mono}', ui-monospace, monospace`,
    '--nav-h': t.sel.density === 'compact' ? '56px' : '72px',
  }
}
