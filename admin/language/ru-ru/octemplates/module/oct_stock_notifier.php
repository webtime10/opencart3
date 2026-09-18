<?php
// Заголовки
$_['heading_title']          = 'Octemplates - Уведомление о наличии товара';

// Текст
$_['text_extension']         = 'Расширения';
$_['text_success']           = 'Настройки успешно сохранены!';
$_['text_success_deleted']   = 'Удаление прошло успешно!';
$_['text_success_install']   = 'Модуль успешно установлен!';
$_['text_edit']              = 'Редактирование настроек';
$_['text_enabled']           = 'Включено';
$_['text_enabled_required']  = 'Включено и обязательно';
$_['text_disabled']          = 'Выключено';
$_['text_yes']               = 'Да';
$_['text_no']                = 'Нет';
$_['text_pagination']        = 'Показано с %d по %d из %d (всего страниц %d)';
$_['text_no_results']        = 'Нет данных!';
$_['text_email']             = 'Пользователь\Email';
$_['text_product_name']      = 'Название товара';
$_['text_phone']             = 'Телефон';
$_['entry_name']             = 'Отображать поле «Имя»?';
$_['entry_phone']            = 'Отображать поле «Телефон»?';
$_['entry_mask']             = 'Маска номера телефона';
$_['entry_mask_info']        = 'Введите число 9 для обозначения маски числа в номере телефона. Например +38 (999) 999-99-99';
$_['text_subscribed_date']   = 'Дата подписки';
$_['text_status']            = 'Статус';
$_['text_status_processed']  = 'Обработано';
$_['text_status_pending']    = 'Ожидает';
$_['text_notified_date']     = 'Дата уведомления';
$_['text_filter_email']      = 'Email';
$_['text_filter_phone']      = 'Телефон';
$_['text_filter_product']    = 'Товар';
$_['text_filter_status']     = 'Статус';
$_['text_copy']              = 'Копировать';
$_['text_all']               = 'Все';
$_['text_email_heading']     = 'Письмо уведомления покупателям при поступлении товара на склад';
$_['text_notify_subject']    = 'Заголовок письма';
$_['text_notify_message']    = 'Текст письма';
$_['text_warning']           = 'Проверьте форму на ошибки!';

// Кнопки
$_['button_delete_menu']     = 'Удалить';
$_['button_delete_selected'] = 'Удалить выбранное';
$_['button_delete_all']      = 'Удалить все';
$_['button_delete']          = 'Удалить';
$_['button_filter']          = 'Фильтр';
$_['button_save']            = 'Сохранить';
$_['button_cancel']          = 'Отмена';

// Вкладки
$_['tab_settings']           = 'Настройки';
$_['tab_subscribers']        = 'Подписчики';
$_['tab_email_templates']    = 'Настройки email шаблона';

// Поля ввода
$_['entry_status']           = 'Статус';
$_['entry_email']            = 'Email для уведомлений';
$_['entry_admin_alert']      = 'Уведомление администратора о новом запросе';
$_['entry_cron_secret']      = 'Секретный ключ для выполнения CRON';
$_['entry_cron_input']       = 'Если вы обновляете остатки товара, добавьте этот URL в CRON в панели хостинга (выполнять раз в день)';
$_['entry_notify_on_edit']   = 'Уведомлять при редактировании товара?<br><div style="font-weight: normal;"><i>Должен быть задан "Секретный ключ для выполнения CRON"</i></div>';
$_['entry_cron']             = 'Задание CRON';
$_['entry_email_codes']      = '[store] - название магазина<br />[product_name] - название товара<br />[product_link] - ссылка на страницу товара<br />[customer_name] - имя покупателя';

// Помощь
$_['help_email']             = 'Введите email через запятую, для отправки на несколько адресов';

// Email
$_['mail_welcome']           = 'Добро пожаловать и информируем вас о том, что на сайте "%s" товар снова в наличии!';
$_['mail_subject']           = '%s - %s снова в наличии!';
$_['mail_body']              = 'Вы подписывались на наличие товара: "%s" - %s';

// Ошибка
$_['error_permission']       = 'У вас нет прав для изменения настроек!';
$_['error_email']            = 'Email обязателен!';
$_['error_valid_email']      = 'Введите корректный email!';
$_['error_warning_info']     = 'Внимание! Проверьте форму на ошибки!';
$_['error_subject_empty']    = 'Заголовок не должен быть пустым!';
$_['error_message_empty']    = 'Сообщение не может быть пустым и должно содержать не  менее 30 символов!';