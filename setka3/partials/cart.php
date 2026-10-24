<section class="cart-page" data-cart>
  <div class="container">
    <ul class="breadcrumb">
      <li><a href="index.php">Головна</a></li>
      <li>Кошик покупок</li>
    </ul>

    <h1 class="cart-title">Ваші покупки</h1>

    <div class="cart-layout">
      <div class="cart-main">
        <article class="cart-item" data-cart-item>
          <a class="cart-item-media" href="product.php">
            <img src="img/product/1.jpg" alt="" width="120" height="120" loading="lazy">
          </a>
          <div class="cart-item-info">
            <div class="cart-item-sku">EP12-20M5</div>
            <a class="cart-item-name" href="product.php">Батарея до ДБЖ Europower 12В 20Аг (EP12-20M5)</a>
          </div>
          <div class="qty cart-qty" data-qty>
            <button type="button" data-qty-minus aria-label="Менше">‹</button>
            <input class="form-control" data-qty-input type="number" value="2" min="1" max="99" inputmode="numeric">
            <button type="button" data-qty-plus aria-label="Більше">›</button>
          </div>
          <div class="cart-item-price">
            <strong>3 168 ₴</strong>
            <span>1 584 ₴ / шт.</span>
          </div>
          <button type="button" class="cart-remove" data-cart-remove aria-label="Видалити">
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
          </button>
        </article>

        <article class="cart-item" data-cart-item>
          <a class="cart-item-media" href="product.php">
            <img src="img/product/2.jpg" alt="" width="120" height="120" loading="lazy">
          </a>
          <div class="cart-item-info">
            <div class="cart-item-sku">CL-0003</div>
            <a class="cart-item-name" href="product.php">Classic Leather #3</a>
          </div>
          <div class="qty cart-qty" data-qty>
            <button type="button" data-qty-minus aria-label="Менше">‹</button>
            <input class="form-control" data-qty-input type="number" value="1" min="1" max="99" inputmode="numeric">
            <button type="button" data-qty-plus aria-label="Більше">›</button>
          </div>
          <div class="cart-item-price">
            <strong>2 490 ₴</strong>
            <span>2 490 ₴ / шт.</span>
          </div>
          <button type="button" class="cart-remove" data-cart-remove aria-label="Видалити">
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
          </button>
        </article>
      </div>

      <aside class="cart-aside">
        <div class="cart-sticky">
          <section class="cart-card">
            <dl class="cart-totals">
              <div><dt>Товари</dt><dd>5 658 ₴</dd></div>
              <div><dt>Знижка</dt><dd>0 ₴</dd></div>
              <div class="is-total"><dt>Всього</dt><dd>5 658 ₴</dd></div>
            </dl>
            <a class="btn btn-primary btn-lg btn-block" href="checkout.php">Оформлення замовлення</a>
            <button type="button" class="btn btn-default btn-lg btn-block" data-quick-order>Швидке замовлення</button>
            <p class="cart-note">Наш менеджер зателефонує вам і оформить замовлення за вас</p>
          </section>

          <section class="cart-card">
            <div class="cart-card-head">
              <h2>Купон</h2>
              <a href="#">Ввести сертифікат</a>
            </div>
            <div class="cart-coupon">
              <input class="form-control" type="text" name="coupon" placeholder="Введіть код купона">
              <button type="button" class="btn btn-default">Застосувати</button>
            </div>
          </section>

          <section class="cart-card cart-acc" data-accordion>
            <button type="button" class="cart-acc-btn" data-accordion-btn aria-expanded="false">
              Вартість доставки
              <svg class="icon icon-sm" aria-hidden="true"><use href="#i-chevron"></use></svg>
            </button>
            <div class="cart-acc-panel" data-accordion-panel hidden>
              <p>Доставка розраховується на кроці оформлення замовлення (Нова Пошта, Укрпошта, курʼєр або самовивіз).</p>
            </div>
          </section>
        </div>
      </aside>
    </div>
  </div>
</section>
