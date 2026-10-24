<?php
require_once __DIR__ . '/catalog-lib.php';
$checkout_recs = array_slice(setka_catalog_page(1, 4)['items'], 0, 4);
?>
<section class="wt_checkout_page" data-checkout>
  <div class="wt_container">
    <ul class="wt_breadcrumb">
      <li><a href="index.php">Головна</a></li>
      <li>Оформлення замовлення</li>
    </ul>

    <h1 class="wt_checkout_title">Оформлення замовлення</h1>

    <form class="wt_checkout_layout" action="#" method="post">
      <div class="wt_checkout_main">
        <section class="wt_checkout_card">
          <div class="wt_checkout_card_head">
            <h2>Інформація клієнта</h2>
            <a href="#">Увійти</a>
          </div>
          <div class="wt_checkout_grid">
            <div class="wt_field">
              <label for="co-firstname">Імʼя</label>
              <input class="wt_input" id="co-firstname" name="firstname" type="text" placeholder="Імʼя" autocomplete="given-name">
            </div>
            <div class="wt_field">
              <label for="co-lastname">Прізвище</label>
              <input class="wt_input" id="co-lastname" name="lastname" type="text" placeholder="Прізвище" autocomplete="family-name">
            </div>
            <div class="wt_field">
              <label for="co-phone">Номер телефону</label>
              <div class="wt_input_split">
                <select class="wt_select" name="phone_code" aria-label="Код країни">
                  <option value="+380" selected>+380</option>
                  <option value="+48">+48</option>
                  <option value="+1">+1</option>
                </select>
                <input class="wt_input" id="co-phone" name="phone" type="tel" placeholder="00 000 00 00" autocomplete="tel-national">
              </div>
            </div>
            <div class="wt_field">
              <label for="co-email">E-mail</label>
              <input class="wt_input" id="co-email" name="email" type="email" placeholder="name@email.com" autocomplete="email">
            </div>
          </div>
          <label class="wt_check">
            <input type="checkbox" name="register" value="1">
            Я хочу зареєструватись
          </label>
        </section>

        <section class="wt_checkout_card">
          <h2>Способи доставки</h2>
          <div class="wt_method_list">
            <div class="wt_method_item is-active" data-method-group="shipping">
              <label class="wt_method_row">
                <span class="wt_method_left">
                  <input type="radio" name="shipping" value="nova" checked>
                  <svg class="wt_icon wt_icon_sm" aria-hidden="true"><use href="#i-truck"></use></svg>
                  <span>Нова Пошта</span>
                </span>
                <strong>70.00 ₴</strong>
              </label>
              <div class="wt_method_panel" data-method-panel>
                <div class="wt_checkout_grid">
                  <div class="wt_field">
                    <label for="np-city">Місто</label>
                    <input class="wt_input" id="np-city" name="np_city" type="text" placeholder="Одеса">
                  </div>
                  <div class="wt_field">
                    <label for="np-office">Відділення / поштомат</label>
                    <input class="wt_input" id="np-office" name="np_office" type="text" placeholder="№ відділення">
                  </div>
                </div>
              </div>
            </div>

            <div class="wt_method_item" data-method-group="shipping">
              <label class="wt_method_row">
                <span class="wt_method_left">
                  <input type="radio" name="shipping" value="ukr">
                  <svg class="wt_icon wt_icon_sm" aria-hidden="true"><use href="#i-truck"></use></svg>
                  <span>Укрпошта</span>
                </span>
                <strong>55.00 ₴</strong>
              </label>
              <div class="wt_method_panel" data-method-panel hidden>
                <div class="wt_field" style="margin:0">
                  <label for="ukr-office">Індекс / відділення</label>
                  <input class="wt_input" id="ukr-office" name="ukr_office" type="text" placeholder="65000">
                </div>
              </div>
            </div>

            <div class="wt_method_item" data-method-group="shipping">
              <label class="wt_method_row">
                <span class="wt_method_left">
                  <input type="radio" name="shipping" value="courier">
                  <svg class="wt_icon wt_icon_sm" aria-hidden="true"><use href="#i-truck"></use></svg>
                  <span>Курʼєрська доставка</span>
                </span>
                <strong>120.00 ₴</strong>
              </label>
              <div class="wt_method_panel" data-method-panel hidden>
                <div class="wt_field" style="margin:0">
                  <label for="courier-address">Адреса</label>
                  <input class="wt_input" id="courier-address" name="courier_address" type="text" placeholder="Вулиця, будинок, квартира">
                </div>
              </div>
            </div>

            <div class="wt_method_item" data-method-group="shipping">
              <label class="wt_method_row">
                <span class="wt_method_left">
                  <input type="radio" name="shipping" value="pickup">
                  <svg class="wt_icon wt_icon_sm" aria-hidden="true"><use href="#i-store"></use></svg>
                  <span>Самовивіз з магазину</span>
                </span>
                <strong>0.00 ₴</strong>
              </label>
              <div class="wt_method_panel" data-method-panel hidden>
                <div class="wt_checkout_note">Одеса, вул. Приклад 1 · Пн–Сб 10:00–19:00</div>
              </div>
            </div>
          </div>
        </section>

        <section class="wt_checkout_card">
          <h2>Способи оплати</h2>
          <div class="wt_method_list">
            <div class="wt_method_item is-active" data-method-group="payment">
              <label class="wt_method_row">
                <span class="wt_method_left">
                  <input type="radio" name="payment" value="bank" checked>
                  <svg class="wt_icon wt_icon_sm" aria-hidden="true"><use href="#i-wallet"></use></svg>
                  <span>Банківський переказ</span>
                </span>
              </label>
              <div class="wt_method_panel" data-method-panel>
                <div class="wt_field" style="margin:0">
                  <label for="payer-name">ПІБ платника</label>
                  <input class="wt_input" id="payer-name" name="payer_name" type="text" placeholder="Прізвище Імʼя По батькові">
                </div>
              </div>
            </div>

            <div class="wt_method_item" data-method-group="payment">
              <label class="wt_method_row">
                <span class="wt_method_left">
                  <input type="radio" name="payment" value="cod">
                  <svg class="wt_icon wt_icon_sm" aria-hidden="true"><use href="#i-wallet"></use></svg>
                  <span>Накладений платіж</span>
                </span>
              </label>
            </div>

            <div class="wt_method_item" data-method-group="payment">
              <label class="wt_method_row">
                <span class="wt_method_left">
                  <input type="radio" name="payment" value="liqpay">
                  <svg class="wt_icon wt_icon_sm" aria-hidden="true"><use href="#i-wallet"></use></svg>
                  <span>LIQPAY</span>
                </span>
              </label>
            </div>
          </div>
        </section>

        <section class="wt_checkout_card">
          <h2>Коментар до замовлення</h2>
          <div class="wt_field" style="margin:0">
            <label class="wt_visually_hidden" for="co-comment">Коментар</label>
            <textarea class="wt_input wt_textarea" id="co-comment" name="comment" rows="4" placeholder="Ваш коментар"></textarea>
          </div>
        </section>

        <section class="wt_checkout_card">
          <h2>Товари в кошику</h2>
          <div class="wt_checkout_items">
            <article class="wt_checkout_item">
              <a class="wt_checkout_item_media" href="product.php" aria-label="Товар">
                <img src="img/product/1.jpg" alt="" width="88" height="88" loading="lazy">
              </a>
              <div class="wt_checkout_item_body">
                <a class="wt_checkout_item_title" href="product.php">Батарея до ДБЖ Europower 12В 20Аг (EP12-20M5)</a>
                <div class="wt_checkout_item_meta">Код: EP12-20M5</div>
                <div class="wt_checkout_item_row">
                  <div class="wt_qty" data-qty>
                    <button type="button" data-qty-minus aria-label="Менше">−</button>
                    <input class="wt_input" data-qty-input type="number" name="qty[1]" value="1" min="1" max="99" inputmode="numeric">
                    <button type="button" data-qty-plus aria-label="Більше">+</button>
                  </div>
                  <div class="wt_checkout_item_price">
                    <span>1 584 ₴</span>
                    <strong>1 584 ₴</strong>
                  </div>
                </div>
              </div>
              <button type="button" class="wt_checkout_item_remove" aria-label="Видалити">
                <svg class="wt_icon wt_icon_sm"><use href="#i-trash"></use></svg>
              </button>
            </article>

            <article class="wt_checkout_item">
              <a class="wt_checkout_item_media" href="product.php" aria-label="Товар">
                <img src="img/product/2.jpg" alt="" width="88" height="88" loading="lazy">
              </a>
              <div class="wt_checkout_item_body">
                <a class="wt_checkout_item_title" href="product.php">Classic Leather #3</a>
                <div class="wt_checkout_item_meta">Код: CL-0003</div>
                <div class="wt_checkout_item_row">
                  <div class="wt_qty" data-qty>
                    <button type="button" data-qty-minus aria-label="Менше">−</button>
                    <input class="wt_input" data-qty-input type="number" name="qty[2]" value="2" min="1" max="99" inputmode="numeric">
                    <button type="button" data-qty-plus aria-label="Більше">+</button>
                  </div>
                  <div class="wt_checkout_item_price">
                    <span>2 490 ₴</span>
                    <strong>4 980 ₴</strong>
                  </div>
                </div>
              </div>
              <button type="button" class="wt_checkout_item_remove" aria-label="Видалити">
                <svg class="wt_icon wt_icon_sm"><use href="#i-trash"></use></svg>
              </button>
            </article>
          </div>
        </section>

        <section class="wt_checkout_card">
          <h2>Рекомендовані товари</h2>
          <div class="wt_products wt_products--catalog wt_checkout_recs">
            <?= setka_render_product_cards($checkout_recs) ?>
          </div>
        </section>
      </div>

      <aside class="wt_checkout_aside">
        <div class="wt_checkout_sticky">
          <section class="wt_checkout_card">
            <h2 class="wt_checkout_aside_title">Що ще бажаєте зробити?</h2>

            <div class="wt_checkout_acc" data-accordion>
              <button type="button" class="wt_checkout_acc_btn" data-accordion-btn aria-expanded="false">
                Використати купон
                <svg class="wt_icon wt_icon_sm" aria-hidden="true"><use href="#i-chevron"></use></svg>
              </button>
              <div class="wt_checkout_acc_panel" data-accordion-panel hidden>
                <div class="wt_checkout_inline">
                  <input class="wt_input" type="text" name="coupon" placeholder="Код купона">
                  <button type="button" class="wt_btn wt_btn_secondary">Застосувати</button>
                </div>
              </div>
            </div>

            <div class="wt_checkout_acc" data-accordion>
              <button type="button" class="wt_checkout_acc_btn" data-accordion-btn aria-expanded="false">
                Використати сертифікат
                <svg class="wt_icon wt_icon_sm" aria-hidden="true"><use href="#i-chevron"></use></svg>
              </button>
              <div class="wt_checkout_acc_panel" data-accordion-panel hidden>
                <div class="wt_checkout_inline">
                  <input class="wt_input" type="text" name="voucher" placeholder="Код сертифіката">
                  <button type="button" class="wt_btn wt_btn_secondary">Застосувати</button>
                </div>
              </div>
            </div>

            <div class="wt_checkout_progress">
              <div class="wt_checkout_progress_text">
                До безкоштовної доставки залишилось <strong>436 ₴</strong>
              </div>
              <div class="wt_checkout_progress_bar" aria-hidden="true">
                <span style="width:78%"></span>
              </div>
            </div>

            <dl class="wt_checkout_totals">
              <div><dt>Підсумок</dt><dd>6 564 ₴</dd></div>
              <div><dt>Доставка</dt><dd>70 ₴</dd></div>
              <div class="is-total"><dt>Разом</dt><dd>6 634 ₴</dd></div>
            </dl>

            <div class="wt_checkout_agree">
              <label class="wt_check"><input type="checkbox" name="no_call" value="1"> Не телефонувати мені</label>
              <label class="wt_check"><input type="checkbox" name="messengers" value="1"> Звʼязатись у месенджерах</label>
              <label class="wt_check"><input type="checkbox" name="agree" value="1" required> Я погоджуюсь з умовами</label>
            </div>

            <button type="submit" class="wt_btn wt_btn_lg wt_btn_block">Оформити замовлення</button>
          </section>
        </div>
      </aside>
    </form>
  </div>
</section>
