<section class="section" style="padding-top:0">
  <div class="container">
    <h2 class="section-title">Кнопки · поля · select · checkbox</h2>

    <div class="controls-demo">
      <div>
        <div class="form-group">
          <label for="name">Імʼя</label>
          <input class="form-control" id="name" type="text" placeholder="Ваше імʼя">
        </div>
        <div class="form-group">
          <label for="city">Місто</label>
          <select class="form-control" id="city">
            <option>Одеса</option>
            <option>Київ</option>
            <option>Львів</option>
            <option>Харків</option>
          </select>
        </div>
        <div class="form-group">
          <label for="note">Коментар</label>
          <textarea class="form-control" id="note" placeholder="Текст..."></textarea>
        </div>
      </div>

      <div>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
          <button type="button" class="btn btn-primary">Primary</button>
          <button type="button" class="btn btn-default">Secondary</button>
          <button type="button" class="btn btn-primary btn-sm">Small</button>
        </div>

        <label class="checkbox"><input type="checkbox" checked> Повідомляти про знижки</label>
        <label class="checkbox"><input type="checkbox"> Погоджуюсь з умовами</label>

        <div style="margin-top:1rem">
          <label class="radio"><input type="radio" name="pay" checked> Онлайн</label>
          <label class="radio"><input type="radio" name="pay"> Накладений платіж</label>
        </div>

        <div style="margin-top:1.25rem;padding:1rem;background:var(--header-bg);border-radius:var(--radius-lg)">
          <button type="button" class="btn btn-link">Ghost on header</button>
        </div>

        <div style="margin-top:1.5rem">
          <h3 class="section-title" style="font-size:16px">Bootstrap 3 · модулі</h3>
          <div class="alert alert-danger">alert-danger</div>
          <div class="form-group has-error">
            <label class="control-label" for="bs3-email">E-mail</label>
            <input type="email" class="form-control" id="bs3-email" placeholder="name@email.com">
            <span class="help-block">Це поле обовʼязкове</span>
          </div>
          <p>
            <button type="button" class="btn btn-primary">btn-primary</button>
            <button type="button" class="btn btn-default">btn-default</button>
            <button type="button" class="btn btn-danger btn-sm">btn-danger</button>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
