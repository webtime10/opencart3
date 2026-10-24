<section class="section" style="padding-top:0">
  <div class="container">
    <h2 class="section-title">Кнопки · поля · select · checkbox</h2>

    <div class="controls-demo">
      <div>
        <div class="field">
          <label for="name">Імʼя</label>
          <input class="form-control" id="name" type="text" placeholder="Ваше імʼя">
        </div>
        <div class="field">
          <label for="city">Місто</label>
          <select class="form-select" id="city">
            <option>Одеса</option>
            <option>Київ</option>
            <option>Львів</option>
            <option>Харків</option>
          </select>
        </div>
        <div class="field">
          <label for="note">Коментар</label>
          <textarea class="form-control" id="note" placeholder="Текст..."></textarea>
        </div>
      </div>

      <div>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
          <button type="button" class="btn btn-primary">Primary</button>
          <button type="button" class="btn btn-secondary">Secondary</button>
          <button type="button" class="btn btn-primary btn-sm">Small</button>
        </div>

        <label class="form-check"><input type="checkbox" checked> Повідомляти про знижки</label>
        <label class="form-check"><input type="checkbox"> Погоджуюсь з умовами</label>

        <div style="margin-top:1rem">
          <label class="form-check"><input type="radio" name="pay" checked> Онлайн</label>
          <label class="form-check"><input type="radio" name="pay"> Накладений платіж</label>
        </div>

        <div style="margin-top:1.25rem;padding:1rem;background:var(--header-bg);border-radius:var(--radius-lg)">
          <button type="button" class="btn btn-link">Ghost on header</button>
        </div>
      </div>
    </div>
  </div>
</section>
