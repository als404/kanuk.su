<?php
// DT: catalog sort fields (insert immediately before the SECTION component call).
if ($sortMode === 'price') {
    $dtSortField1 = 'PROPERTY_DT_SORT_ZERO';
    $dtSortOrder1 = 'ASC';
    $dtSortField2 = 'PROPERTY_DT_SORT_PRICE';
    $dtSortOrder2 = strtoupper($sortOrder);
    $dtHideNotAvailable = 'N';
} else {
    $dtSortField1 = 'PROPERTY_DT_SORT_GROUP';
    $dtSortOrder1 = 'ASC';
    $dtSortField2 = $sortMode === 'rating'
        ? 'PROPERTY_rating' : 'PROPERTY_DT_SORT_PRICE';
    $dtSortOrder2 = $sortMode === 'rating'
        ? strtoupper($sortOrder) : 'ASC';
    $dtHideNotAvailable = $arParams['HIDE_NOT_AVAILABLE'];
}
?>
