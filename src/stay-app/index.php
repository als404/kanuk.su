<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
<link rel="stylesheet" href="<?=SITE_TEMPLATE_PATH?>/stay-app/stay-app.css">

<div class="stay-app fixed-overlay">
    <div class="stay-app__popup">
        <div class="gift"></div>
        <div class="popup-container">
            <div class="popup-header">
                <div class="popup-title"></div>
                <i class="material-icons popup-close">close</i>
            </div>
            <div class="popup-body">
                <p class="h1">Продадим дешевле!!!</p>
                <p class="h3">Не уходите от нас без покупки - для Вас есть специальное предложение!</p>
                <p>Мы дадим вам скидку до 35% (скидка зависит от того товара, который вы выберете, 
                    и от нашей торговой наценки). Если вы купите товар в течение 24 часов, 
                    мы вам подарим банку масла, газовый ключ, набор инструментов или ещё 
                    что-то полезное для вас. Конечно, сумма подарка зависит от выбранного вами товара.</p>
                <form>
                    <div class="popup-form-group">
                        <label for="actionPhone">Телефон:</label>
                        <input type="text" class="phone" id="actionPhone" placeholder="+7 (___) ___ ____"  maxlength="17" required>
                        
                    </div>
                    <div class="popup-form-group">
                        <label for="actionEmail">E-mail:</label>
                        <input type="email" class="email" id="actionEmail" placeholder="name@mail.ru" required>
                    </div>
                        <button type="submit" class="popup-btn-send">Отправить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>let pathTmpl = "<?=SITE_TEMPLATE_PATH?>";</script>
<script src="<?=SITE_TEMPLATE_PATH?>/stay-app/stay-app.js"></script>