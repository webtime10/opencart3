

/*
сюда мы кидаем ключи ()
oc_wt_filter_option
oc_wt_filter_option_description
в случае с атрибутом (харктеристка-змейка)
в случае с опцией (опция - цвет)
*/
/*
сюда мы кидаем значения
oc_wt_filter_option_value
oc_wt_filter_option_value_description
в случае с атрибутом (харктеристка-волнистая)
в случае с опцией (опция - жёлтый)
*/
/*
oc_wt_filter_option_value_to_product таблица связей
*/
/*
oc_wt_filter_option_to_category  в какой категории

*/



1. Таблицы характеристик (групп фильтров)
oc_wt_filter_option (структура и флаги UI)


Откуда берём:

oc_option (опции)

oc_filter_group (стандартные фильтры, ID + 10000)

oc_attribute (атрибуты, ID + 30000)

Куда кладём: oc_wt_filter_option

oc_wt_filter_option_description (названия групп на разных языках)

Откуда берём:

oc_option_description

oc_filter_group_description (ID + 10000)

oc_attribute_description (ID + 30000)

Куда кладём: oc_wt_filter_option_description

2. Таблицы значений фильтров
oc_wt_filter_option_value (самы значения: Красный, XL, 16 ГБ)

Откуда берём:

oc_option_value

oc_filter (ID + 10000)

oc_product_attribute (уникальные тексты, сгенерированный SHA1 value_id)

Куда кладём: oc_wt_filter_option_value

oc_wt_filter_option_value_description (переводы названий значений)

Откуда берём:

oc_option_value_description

oc_filter_description (ID + 10000)

oc_product_attribute (тексты атрибутов по всем языкам)

Куда кладём: oc_wt_filter_option_value_description

3. Таблицы связей (товар, категория, магазин)
oc_wt_filter_option_value_to_product (какие значения у каких товаров)

Откуда берём:

oc_product_option_value (опции с остатком > 0)

oc_product_filter (штатные фильтры)

oc_product_attribute (атрибуты + выборка чисел для слайдера)

Куда кладём: oc_wt_filter_option_value_to_product
+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
oc_wt_filter_option_to_category (в каких категориях выводить фильтры)

Откуда берём: Связка oc_wt_filter_option_value_to_product + oc_product_to_category
 /*
Именно так!

Он джоинит таблицы по совпадающему product_id.

В базе данных product_id выступает в роли «моста» (внешнего ключа). Сам по себе category_id ничего не знает про опции, а option_id ничего не знает про категории. Но они оба знают, к какому product_id привязаны.

Схема соединения в запросе работает прямо по цепочке:
Берём запись из oc_wt_filter_option_value_to_product (там есть product_id и option_id).
Ищем в oc_product_to_category точно такой же product_id.
Достаём привязанный к этому товару category_id.
Сохраняем готовую пару (option_id, category_id) в таблицу oc_wt_filter_option_to_category.
 */ /* нужна для фронта чтоб привязать категории*/

Куда кладём: oc_wt_filter_option_to_category
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++==
oc_wt_filter_option_to_store (в каких магазинах выводить фильтры)

Откуда берём: oc_wt_filter_option + массив выбранных store_id из настройки формы

Куда кладём: oc_wt_filter_option_to_store
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++


                                      


