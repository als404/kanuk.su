window.addEventListener('DOMContentLoaded', () => {
'use strict';

  (() => {
    
    const wrapBlackFriday = document.querySelector('.black-friday__wrap');
    const promotionsDetail = document.querySelector('.promotions-detail .promotions-detail__date-wrap');
    
    insertAfter(wrapBlackFriday, promotionsDetail);
    
    function insertAfter(object, transfer) {
        if(object && transfer){
            transfer.after(object);
        }
    }

  })();

});