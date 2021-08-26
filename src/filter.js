'use strict';

document.addEventListener('DOMContentLoaded', () =>{
    (() => {

        const filterBox = document.querySelector('.filter table tbody'), 
            filterList = filterBox.querySelectorAll('.bx_filter_box');

        function start(parent) {
            let tr = document.createElement('tr');
            tr.innerHTML = '<tr style="display: block;"><td colspan="2" style="text-align:center; color:#cd3367; cursor:pointer;"><div class="btn__show_filter">Показать все параметры</div></td></tr>';
            parent.append(tr);
            const toogleFilter = document.querySelector('.catalog_item_toogle_filter');
            if (!toogleFilter.classList.contains('active')) {
                toogleFilter.classList.add('active');
                document.querySelector('.filter').style.display = 'block';
            }
            
        }

        function show(elem) {
            elem.style.display = 'block';
        }
        
        function hide(elem) {
            elem.style.display = 'none';
        }
        
        function hideProps(boxs, btn) {
            boxs.forEach(item => {
                const name = item.children[0].innerText;
                if (name.indexOf('Розничная цена') == 0 || name.indexOf('Производитель') == 0) {
                    show(item);
                } else {
                    hide(item)
                }     
            });

            if(btn) {
                btn.classList = 'btn__show_filter';
                btn.innerText = 'Показать все параметры';
                btn.addEventListener('click', () => {
                    showProps(filterList, btn);
                });
            }
        }
        
        function showProps(boxs, btn) {
            boxs.forEach(item => {
                if (item.style.display == 'none') {
                    show(item);
                }
            });

            btn.classList = 'btn__hide_filter';
            btn.innerText = 'Скрыть параметры';

            btn.addEventListener('click', () => {
                hideProps(filterList, btn);
            });
        }
        
        start(filterBox);

        const btnShowFilter = document.querySelector('.btn__show_filter');

        hideProps(filterList, btnShowFilter);

        btnShowFilter.addEventListener('click', () => {
            showProps(filterList, btnShowFilter);
        });
   
    })(); 
});
