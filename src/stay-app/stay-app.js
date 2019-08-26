window.addEventListener('DOMContentLoaded', function() {
    'use strict';

    (() => {
        // Modal
        class Modal {
            constructor() {
                this.overlay = document.querySelector('.stay-app.fixed-overlay');
                this.closeBtn = this.overlay.querySelector('.popup-close');
                this.c = () => this.closeBtn.addEventListener('click', this.close);
                this.c();
            }

            show(){
                document.querySelector('.stay-app.fixed-overlay').style.display = 'block';
                document.body.style.overflow = 'hidden';
            }

            close(){
                document.querySelector('.stay-app.fixed-overlay').style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        const modal = new Modal();

//      const more = document.querySelector('.more');
//      more.addEventListener('click', modal.show);

        // Правильные таймеры для блока "не уходите".
        if(document.cookie.indexOf('exit_block=') == -1 && document.cookie.indexOf('exit_block_timer=') == -1){
            const expire = new Date();
            expire.setTime(expire.getTime() + 1000 * 5);
            document.cookie = "exit_block_time=1;expires="+expire.toGMTString();
            document.cookie = "exit_block_timer=1;";
        }

        // проверяем, есть ли у нас cookie, с которой мы не показываем окно 
        // и если нет, запускаем показ
        let exitBlock = getCookie("exit_block");
        let mouseY, mouseYO;

        if (exitBlock != 1) {
            // Получаем вертикальные координаты курсора
            document.addEventListener('mousemove', function(event) {
                window.mouseYO = window.mouseY;
                window.mouseY = event.pageY;
            });
            // если клиент уходит со страницы, показываем ему модаль
            // событие срабатывае только если курсор поднимается вверх
            document.body.addEventListener('mouseleave', function(event){
                if(window.mouseY < window.mouseYO){
                    if (exitBlock != 1) {
                        exitBlock = 1; // засчитываем посещение
                        SetCookie('exit_block', '1', 14); // учтанавливаем cookie
                        modal.show(); // запускаем модальное окно
                    }
                }
            });
        }

        // функция возвращает cookie с именем name, если есть, если нет, то undefined    
        function getCookie(name) {
            const matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
            ));
            return matches ? decodeURIComponent(matches[1]) : undefined;
        }

        // Установка новых кук.
        // @param cookieName
        // @param cookieValue
        // @param nDays
        function SetCookie(cookieName, cookieValue, nDays) {
            let today = new Date();
            let expire = new Date();
            if (nDays == null || nDays == 0) { 
                nDays = 1; 
            }
            expire.setTime(today.getTime() + 3600000*24*nDays);
            document.cookie = `${cookieName} = ${escape(cookieValue)} ;expires= ${expire.toGMTString()}`;
        }

//Send Form on Server
        const btnSend = document.getElementsByClassName('popup-btn-send')[0];
        btnSend.addEventListener('click', function(event) {
            event.preventDefault();
            const formData = {
                phone: document.getElementById('actionPhone').value,
                email: document.getElementById('actionEmail').value,
                url: window.location.href
            };
            const { phone, email } = formData;

            if(validPhone(phone) && validEmail(email)){
                const msgBox = document.getElementById('msg');
                sendFormAjax(formData)
                    .then(data => {
                        msgBox.innerHTML = data;
                    })
                    .catch(error => console.log(error));
                modal.close();
            } else {
                checkInput(validPhone, 'actionPhone');
                checkInput(validEmail, 'actionEmail');
            }
            
        });

        const popupForm = document.querySelector('.stay-app__popup form');
        
        popupForm.addEventListener('focusin', function(event) {
            clearStyleValid(event.target, {valid: 'valid', invalid: 'invalid'});
        });
        
        popupForm.addEventListener('focusout', function(event) {
            const target = event.target;
            if(target.classList.contains('phone') && validPhone(target.value)) {
                addStyleValid(target,'valid');
            } else if(target.classList.contains('phone') && target.value.replace(/\D/g, '').length == 0) {
                clearStyleValid(target, {valid: 'valid', invalid: 'invalid'});
            } else if(target.classList.contains('phone') && target.value.replace(/\D/g, '').length < 11){
                addStyleValid(target,'invalid');
            }

            if(target.classList.contains('email') && validEmail(target.value)) {
                addStyleValid(target,'valid');
            } else if(target.classList.contains('email') && target.value == 0) {
                clearStyleValid(target, {valid: 'valid', invalid: 'invalid'});
            } else if(target.classList.contains('email') && !validEmail(target.value)) {
                addStyleValid(target,'invalid');
            }

        });
        
        // Check input validation, add CSS style for field
        // @func = <function>
        // @id = <integer>
        function checkInput(func, id){
            const field = document.getElementById(id);

            if(!func(field.value)){
                addStyleValid(field,'invalid');
                clearStyleValid(field, {invalid: 'valid'});
            } else {
                addStyleValid(field,'valid');
                clearStyleValid(field, {invalid: 'invalid'});
            }
        } 

        // Add CSS style validation
        // @i = input
        // @s = style name <string>
        function addStyleValid(i,s) {
            i.classList.add(s);
        }

        // Clear CSS style validation
        // @t = event.target
        // @obj = <object>
        function clearStyleValid(t, obj = {valid: 'valid', invalid: 'invalid'}) {
            for(let k in obj) {
                if(t.classList.contains(obj[k])) {
                    t.classList.remove(obj[k]);
                } 
            }
        }

        // AJAX
        // @data = <object>
        // @return = json.object
        function sendFormAjax(data) {
            return fetch('stay-app.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'aplication/json, charset=utf-8',
                },
                body: JSON.stringify(data),
            })
            .then(response => response.json());
        }

        // validate Phone
        // @t = <string>
        // @return = <boolean>
        function validPhone(t) {
            let r = false;
            if( t.replace(/\D/g, '').length == 11) {
                r = true;
            }
            return r;
        }

        // validate Email
        // @t = <string>
        // @return = <boolean>
        function validEmail(m) {
            let r = false;
            if(/^[a-z0-9][a-z0-9-_\.]+@([a-z]|[a-z0-9]?[a-z0-9-]+[a-z0-9])\.[a-z0-9]{2,10}(?:\.[a-z]{2,10})?$/.test(m)) {
                r = true;
            } 
            return r;
        }

        // mask Phone
        // @input = input field
        [].forEach.call( document.querySelectorAll('#actionPhone'), function(input) {
            let keyCode;
            function mask(event) {
                event.keyCode && (keyCode = event.keyCode);
                let pos = this.selectionStart;
                if (pos < 3) event.preventDefault();
                let matrix = "+7 (___) ___ ____",
                    i = 0,
                    def = matrix.replace(/\D/g, ""),
                    val = this.value.replace(/\D/g, ""),
                    new_value = matrix.replace(/[_\d]/g, function(a) {
                        return i < val.length ? val.charAt(i++) || def.charAt(i) : a;
                    });
                i = new_value.indexOf("_");
                if (i != -1) {
                    i < 5 && (i = 3);
                    new_value = new_value.slice(0, i);
                }
                let reg = matrix.substr(0, this.value.length).replace(/_+/g,
                    function(a) {
                        return "\\d{1," + a.length + "}";
                    }).replace(/[+()]/g, "\\$&");
                reg = new RegExp("^" + reg + "$");
                if (!reg.test(this.value) || this.value.length < 5 || keyCode > 47 && keyCode < 58) this.value = new_value;
                if (event.type == "blur" && this.value.length < 5)  this.value = "";
            }

            input.addEventListener("input", mask, false);
            input.addEventListener("focus", mask, false);
            input.addEventListener("blur", mask, false);
            input.addEventListener("keydown", mask, false)
        });

    })();

});