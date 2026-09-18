<?php
// Heading
$_['heading_title']             = 'Octemplates - Авторизация по SMS (OTP)';

// Text
$_['text_extension']            = 'Расширения';
$_['text_success']              = 'Настройки модуля успешно изменены!';
$_['text_edit']                 = 'Редактирование модуля Авторизации по телефону';
$_['text_enabled']              = 'Включено';
$_['text_disabled']             = 'Отключено';
$_['text_install_success']      = 'Модуль успешно установлен!';
$_['text_uninstall_success']    = 'Модуль успешно удален!';
$_['text_logs']                 = 'Логи OTP';
$_['text_list']                 = 'Список логов';
$_['text_pagination']           = 'Показано от %d до %d из %d (%d Страниц)';
$_['text_success_logs_deleted'] = 'Логи успешно удалены!';
$_['text_loading']              = 'Загрузка...';
$_['text_no_results']           = 'Нет данных для отображения.';
$_['text_confirm_delete']       = 'Вы уверены, что хотите удалить все логи?';
$_['text_success_logs_deleted'] = 'Логи успешно удалены!';
$_['text_success_ip_logs_deleted'] = 'Логи IP успешно удалены!';
$_['help_ip_logs']              = 'Этот список содержит все IP-адреса, которые пытались авторизоваться и ввели неверный номер телефона или код OTP.';

// Entry
$_['entry_status']              = 'Статус';
$_['entry_otp_length']          = 'Длина OTP';
$_['entry_otp_expiry']          = 'Срок действия OTP (минуты)';
$_['entry_max_attempts']        = 'Максимальное количество попыток';
$_['entry_lockout_time']        = 'Время блокировки (минуты)';
$_['entry_throttle_time']       = 'Время между запросами (секунды)';
$_['entry_phone_mask']          = 'Маска телефона';
$_['entry_logging']             = 'Включить логирование';
$_['entry_customer_id']         = 'ID Клиента';
$_['entry_telephone']           = 'Телефон';

// Help
$_['help_otp_length']           = 'Количество цифр в OTP коде (от 4 до 10).';
$_['help_otp_expiry']           = 'Время в минутах, в течение которого OTP является действительным.';
$_['help_max_attempts']         = 'Максимальное количество неправильных вводов OTP или номера телефона перед блокировкой.';
$_['help_lockout_time']         = 'Время в минутах, в течение которого пользователь будет заблокирован после превышения попыток.';
$_['help_throttle_time']        = 'Минимальное время в секундах между запросами OTP для предотвращения спама.';
$_['help_logging']              = 'Включите для записи попыток OTP в лог.';
$_['help_logs']                 = 'Этот список содержит все попытки ввода OTP пользователями.';

// Error
$_['error_permission']          = 'Внимание: У вас нет прав для изменения модуля Авторизации по телефону!';
$_['error_status']              = 'Статус модуля обязателен!';
$_['error_otp_length']          = 'Длина OTP должна быть числом от 4 до 10!';
$_['error_otp_expiry']          = 'Срок действия OTP должен быть больше 0!';
$_['error_max_attempts']        = 'Максимальное количество попыток должно быть больше 0!';
$_['error_lockout_time']        = 'Время блокировки должно быть больше 0!';
$_['error_throttle_time']       = 'Время между запросами должно быть больше 0!';
$_['error_no_settings']         = 'Настройки модуля не найдены!';
$_['warning_sms_template']      = 'Внимание: Шаблон SMS сообщения не установлен или отключен! Активируйте шаблон "Авторизация по SMS" в модуле "Настройки SMS уведомлений".';

// Tabs
$_['tab_settings']              = 'Настройки';
$_['tab_logs']                  = 'Логи OTP';
$_['tab_ip_logs']               = 'Логи IP';

// Buttons
$_['button_save']               = 'Сохранить';
$_['button_cancel']             = 'Отменить';
$_['button_delete_logs']        = 'Удалить логи';
$_['button_close']              = 'Закрыть';

// Columns
$_['column_log_id']             = 'ID Лога';
$_['column_customer_id']        = 'ID Клиента';
$_['column_telephone']          = 'Телефон';
$_['column_status']             = 'Статус';
$_['column_date_added']         = 'Дата добавления';
$_['column_attempt_id']         = 'ID';
$_['column_ip_address']         = 'IP Адрес';
$_['column_attempt_count']      = 'Количество попыток';
$_['column_last_attempt']       = 'Последняя попытка';