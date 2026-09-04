# WT Filter (витрина OC3)

## Что сделано
AJAX-фильтр категории **без перезагрузки страницы** (jQuery).

## Файлы
- `catalog/controller/extension/module/wt_filter.php` — UI + `refresh` JSON
- `catalog/model/catalog/wt_filter.php` — SQL / счётчики / опции
- `system/helper/wt_filter.php` + `system/config/wt_filter.php` — params
- `catalog/view/.../wt_filter.*` — twig / js / css

## URL
`filter_wt_filter=12:5,8;m:3;p:100-500`
- `;` группы, `:` опция/значения, `,` значения
- спец: `p` цена, `m` бренд, `s` наличие (`in`/`out`)

## Как включить
1. Extensions → Modules → **WT Filter** → Enable + Save
2. Design → Layouts → **Category** → добавить модуль в column_left/right
3. В админке модуля: Copy attributes/options/filters (с магазином)
4. Refresh модификаций (если ставите через OCMOD zip)

## Поток без reload
выбор значения → debounce 280ms → POST `extension/module/wt_filter/refresh`
→ JSON `{ products, pagination, values, url, total }`
→ jQuery меняет `#wt-filter-results`, обновляет счётчики, `history.pushState`
