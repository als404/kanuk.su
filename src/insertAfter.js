window.addEventListener('DOMContentLoaded', () => {
'use strict';

  (() => {
    
    const moveObject = document.querySelector('.black-friday__wrap');
    const pasteAfter = document.querySelector('.promotions-detail .promotions-detail__date-wrap');
    
    insertAfter(moveObject, pasteAfter);
    
    function insertAfter(object, transfer) {
        if(object && transfer){
            transfer.after(object);
        }
    }

  })();

});
