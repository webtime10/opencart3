<?php
require_once __DIR__ . '/catalog-lib.php';
$checkout_recs = array_slice(setka_catalog_page(1, 4)['items'], 0, 4);
?>
<section class="checkout-page" data-checkout>
  <div class="container">
    <ul class="breadcrumb">
      <li><a href="index.php">Головна</a></li>
      <li>Оформлення замовлення</li>
    </ul>

    <h1 class="checkout-title">Оформлення замовлення</h1>

    <form class="checkout-layout" action="#" method="post">
      <div class="checkout-main">
        <section class="checkout-card">
          <div class="checkout-card-head">
            <h2>Інформація клієнта</h2>
            <a href="#">Увійти</a>
          </div>
          <div class="checkout-grid">
            <div class="field">
              <label for="co-firstname">Імʼя</label>
              <input class="form-control" id="co-firstname" name="firstname" type="text" placeholder="Імʼя" autocomplete="given-name">
            </div>
            <div class="field">
              <label for="co-lastname">Прізвище</label>
              <input class="form-control" id="co-lastname" name="lastname" type="text" placeholder="Прізвище" autocomplete="family-name">
            </div>
            <div class="field">
              <label for="co-phone">Номер телефону</label>
              <div class="input-group">
                <select class="form-select" name="phone_code" aria-label="Код країни">
                  <option value="+380" selected>+380</option>
                  <option value="+48">+48</option>
                  <option value="+1">+1</option>
                </select>
                <input class="form-control" id="co-phone" name="phone" type="tel" placeholder="00 000 00 00" autocomplete="tel-national">
              </div>
            </div>
            <div class="field">
              <label for="co-email">E-mail</label>
              <input class="form-control" id="co-email" name="email" type="email" placeholder="name@email.com" autocomplete="email">
            </div>
          </div>
          <label class="form-check">
            <input type="checkbox" name="register" value="1">
            Я хочу зареєструватись
          </label>
        </section>

        <section class="checkout-card">
          <h2>Способи доставки</h2>
          <div class="method-list">
            <div class="method-item is-active" data-method-group="shipping">
              <label class="method-row">
                <span class="method-left">
                  <input type="radio" name="shipping" value="nova" checked>
                  <svg class="icon icon-sm" aria-hidden="true"><use href="#i-truck"></use></svg>
                  <span>Нова Пошта</span>
                </span>
                <strong>70.00 ₴</strong>
              </label>
              <div class="method-panel" data-method-panel>
                <div class="checkout-grid">
                  <div class="field">
                    <label for="np-city">Місто</label>
                    <input class="form-control" id="np-city" name="np_city" type="text" placeholder="Одеса">
                  </div>
                  <div class="field">
                    <label for="np-office">Відділення / поштомат</label>
                    <input class="form-control" id="np-office" name="np_office" type="text" placeholder="№ відділення">
                  </div>
                </div>
              </div>
            </div>

            <div class="method-item" data-method-group="shipping">
              <label class="method-row">
                <span class="method-left">
                  <input type="radio" name="shipping" value="ukr">
                  <svg class="icon icon-sm" aria-hidden="true"><use href="#i-truck"></use></svg>
                  <span>Укрпошта</span>
                </span>
                <strong>55.00 ₴</strong>
              </label>
              <div class="method-panel" data-method-panel hidden>
                <div class="field" style="margin:0">
                  <label for="ukr-office">Індекс / відділення</label>
                  <input class="form-control" id="ukr-office" name="ukr_office" type="text" placeholder="65000">
                </div>
              </div>
            </div>

            <div class="method-item" data-method-group="shipping">
              <label class="method-row">
                <span class="method-left">
                  <input type="radio" name="shipping" value="courier">
                  <svg class="icon icon-sm" aria-hidden="true"><use href="#i-truck"></use></svg>
                  <span>Курʼєрська доставка</span>
                </span>
                <strong>120.00 ₴</strong>
              </label>
              <div class="method-panel" data-method-panel hidden>
                <div class="field" style="margin:0">
                  <label for="courier-address">Адреса</label>
                  <input class="form-control" id="courier-address" name="courier_address" type="text" placeholder="Вулиця, будинок, квартира">
                </div>
              </div>
            </div>

            <div class="method-item" data-method-group="shipping">
              <label class="method-row">
                <span class="method-left">
                  <input type="radio" name="shipping" value="pickup">
                  <svg class="icon icon-sm" aria-hidden="true"><use href="#i-store"></use></svg>
                  <span>Самовивіз з магазину</span>
                </span>
                <strong>0.00 ₴</strong>
              </label>
              <div class="method-panel" data-method-panel hidden>
                <div class="checkout-note">Одеса, вул. Приклад 1 · Пн–Сб 10:00–19:00</div>
              </div>
            </div>
          </div>
        </section>

        <section class="checkout-card">
          <h2>Способи оплати</h2>
          <div class="method-list">
            <div class="method-item is-active" data-method-group="payment">
              <label class="method-row">
                <span class="method-left">
                  <input type="radio" name="payment" value="bank" checked>
                  <svg class="icon icon-sm" aria-hidden="true"><use href="#i-wallet"></use></svg>
                  <span>Банківський переказ</span>
                </span>
              </label>
              <div class="method-panel" data-method-panel>
                <div class="field" style="margin:0">
                  <label for="payer-name">ПІБ платника</label>
                  <input class="form-control" id="payer-name" name="payer_name" type="text" placeholder="Прізвище Імʼя По батькові">
                </div>
              </div>
            </div>

            <div class="method-item" data-method-group="payment">
              <label class="method-row">
                <span class="method-left">
                  <input type="radio" name="payment" value="cod">
                  <svg class="icon icon-sm" aria-hidden="true"><use href="#i-wallet"></use></svg>
                  <span>Накладений платіж</span>
                </span>
              </label>
            </div>

            <div class="method-item" data-method-group="payment">
              <label class="method-row">
                <span class="method-left">
                  <input type="radio" name="payment" value="liqpay">
                  <svg class="icon icon-sm" aria-hidden="true"><use href="#i-wallet"></use></svg>
                  <span>LIQPAY</span>
                </span>
              </label>
            </div>
          </div>
        </section>

        <section class="checkout-card">
          <h2>Коментар до замовлення</h2>
          <div class="field" style="margin:0">
            <label class="visually-hidden" for="co-comment">Коментар</label>
            <textarea class="form-control" id="co-comment" name="comment" rows="4" placeholder="Ваш коментар"></textarea>
          </div>
        </section>

        <section class="checkout-card">
          <h2>Рекомендовані товари</h2>
          <div class="products products-catalog checkout-recs">
            <?= setka_render_product_cards($checkout_recs) ?>
          </div>
        </section>
      </div>

      <aside class="checkout-aside">
        <div class="checkout-sticky">
          <section class="checkout-card">
            <h2 class="checkout-aside-title">Що ще бажаєте зробити?</h2>

            <div class="checkout-acc" data-accordion>
              <button type="button" class="checkout-acc-btn" data-accordion-btn aria-expanded="false">
                Використати купон
                <svg class="icon icon-sm" aria-hidden="true"><use href="#i-chevron"></use></svg>
              </button>
              <div class="checkout-acc-panel" data-accordion-panel hidden>
                <div class="checkout-inline">
                  <input class="form-control" type="text" name="coupon" placeholder="Код купона">
                  <button type="button" class="btn btn-secondary">Застосувати</button>
                </div>
              </div>
            </div>

            <div class="checkout-acc" data-accordion>
              <button type="button" class="checkout-acc-btn" data-accordion-btn aria-expanded="false">
                Використати сертифікат
                <svg class="icon icon-sm" aria-hidden="true"><use href="#i-chevron"></use></svg>
              </button>
              <div class="checkout-acc-panel" data-accordion-panel hidden>
                <div class="checkout-inline">
                  <input class="form-control" type="text" name="voucher" placeholder="Код сертифіката">
                  <button type="button" class="btn btn-secondary">Застосувати</button>
                </div>
              </div>
            </div>

            <div class="checkout-progress">
              <div class="checkout-progress-text">
                До безкоштовної доставки залишилось <strong>436 ₴</strong>
              </div>
              <div class="checkout-progress-bar" aria-hidden="true">
                <span style="width:78%"></span>
              </div>
            </div>

            <dl class="checkout-totals">
              <div><dt>Підсумок</dt><dd>6 564 ₴</dd></div>
              <div><dt>Доставка</dt><dd>70 ₴</dd></div>
              <div class="is-total"><dt>Разом</dt><dd>6 634 ₴</dd></div>
            </dl>

            <div class="checkout-agree">
              <label class="form-check"><input type="checkbox" name="no_call" value="1"> Не телефонувати мені</label>
              <label class="form-check"><input type="checkbox" name="messengers" value="1"> Звʼязатись у месенджерах</label>
              <label class="form-check"><input type="checkbox" name="agree" value="1" required> Я погоджуюсь з умовами</label>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block w-100">Оформити замовлення</button>
          </section>
        </div>
      </aside>
    </form>
  </div>
</section>
