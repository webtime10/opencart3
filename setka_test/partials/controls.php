<section class="wt_section" style="padding-top:0">
  <div class="wt_container">
    <h2 class="wt_section_title">Кнопки · поля · select · checkbox</h2>

    <div class="wt_controls_demo">
      <div>
        <div class="wt_field">
          <label for="name">Імʼя</label>
          <input class="wt_input" id="name" type="text" placeholder="Ваше імʼя">
        </div>
        <div class="wt_field">
          <label for="city">Місто</label>
          <select class="wt_select" id="city">
            <option>Одеса</option>
            <option>Київ</option>
            <option>Львів</option>
            <option>Харків</option>
          </select>
        </div>
        <div class="wt_field">
          <label for="note">Коментар</label>
          <textarea class="wt_textarea" id="note" placeholder="Текст..."></textarea>
        </div>
      </div>

      <div>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
          <button type="button" class="wt_btn">Primary</button>
          <button type="button" class="wt_btn wt_btn_secondary">Secondary</button>
          <button type="button" class="wt_btn wt_btn_sm">Small</button>
        </div>

        <label class="wt_check"><input type="checkbox" checked> Повідомляти про знижки</label>
        <label class="wt_check"><input type="checkbox"> Погоджуюсь з умовами</label>

        <div style="margin-top:1rem">
          <label class="wt_radio"><input type="radio" name="pay" checked> Онлайн</label>
          <label class="wt_radio"><input type="radio" name="pay"> Накладений платіж</label>
        </div>

        <div style="margin-top:1.25rem;padding:1rem;background:var(--header-bg);border-radius:var(--radius-lg)">
          <button type="button" class="wt_btn wt_btn_ghost">Ghost on header</button>
        </div>
      </div>
    </div>
  </div>
</section>
