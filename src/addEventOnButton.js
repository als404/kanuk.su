document.addEventListener('DOMContentLoaded', () =>{
    (() => {
        'use strict';

		class AddEventOnButton {
			addEventOnButton(selector) {
				const btns = document.querySelectorAll(selector);
				
				btns.forEach(btn => {
					btn.addEventListener('click', () => {
						setTimeout(() => {
							const popupID = AddEventOnButton.getPopupID();
							if (popupID) {
								AddEventOnButton.popupClearAlert(popupID);
								document.getElementById(popupID).addEventListener('click', AddEventOnButton.submit);
							}
						}, 200);
					});
				});
			}
		//получаем ID модалього окна
			static getPopupID(){
				const popup = document.querySelectorAll('.popup-window');

				for(let i = 0; i < popup.length; i++){
					if (popup[i].style.display === 'block'){
						return popup[i].getAttribute('id');
					}
				}
			}
		// получаем заголовок окна
			static getPopupTitle(id){
				return document.getElementById(id).querySelector('div.popup-window-titlebar > span').innerHTML;
			}
		// удаляем сообщение об ошибке
			static popupClearAlert(id){
				const popup = document.getElementById(id);

				if (popup) {
					popup.addEventListener('focusin', () => {
						popup.querySelector('.alert').textContent = '';
					});
				}
			}
		// проверяем поля обязательные для заполнения
			static requeridField(formID, btn = false){
				let err = false, 
					rows = document.getElementById(formID).querySelectorAll('.row');

				for (let i = 0; i < rows.length; i++) {
					if (rows[i].querySelector('.mf-req')) {
						const input = rows[i].querySelector('input');

						if (input.value === '') {
							err = true;
							if (btn) {
								btn.disabled = false;
							}
							break;
						}
					}  
				}
			// если нету ошибки перекидываем на страницу благодарности
				if (!err) {
					if (btn) {
						btn.disabled = true;
					}
					document.location.href = '/thank-you/';
				}
			}
		//отправка данных
			static submit(event) {
				const target = event.target;
				if(target.classList.contains('btn_buy')) {
					AddEventOnButton.requeridField(target.form.id, target);
				}
			}
		}

		const newEvent = new AddEventOnButton();
	// событие на кнопке "заказать звонок"
		newEvent.addEventOnButton('.callback_btn');
	// событие на кнопке "заказать товар"
		newEvent.addEventOnButton('.detail-ask-price');
	// событие на кнопке "купить в один клик"
		newEvent.addEventOnButton('.boc_anch');
	// событие на кнопке "нашли дешевле"
		newEvent.addEventOnButton('.cheaper_anch');
	// событие на кнопке "Запросить цену"
		newEvent.addEventOnButton('.apuo');
	// событие на кнопке "Запросить цену"
		newEvent.addEventOnButton('.ask_price_list');
	})(); 
});