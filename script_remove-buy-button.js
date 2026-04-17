//Скрипт скрывает кнопки, стоимость, наличие для товаров, которые были сняты с производства
document.addEventListener('DOMContentLoaded', () =>{
    (() => {
        const zone = document.querySelector('.buy-zone');
        const zoneText = zone.querySelector('.preview-text').innerText;
        const zoneDetail = zone.querySelector('.buy_detail');
        const checkText = 'Снят с производства';
      
        if (zoneText.includes(checkText)) {
             zoneDetail.style.display = 'none';
        } 
   })(); 
});
