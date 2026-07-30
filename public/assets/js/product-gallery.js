(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var mainWrap = document.getElementById('galleryMain');
        var mainImage = document.getElementById('galleryMainImage');
        var thumbs = document.querySelectorAll('.product-gallery-thumb');

        if (mainImage) {
            mainWrap.addEventListener('click', function () {
                mainWrap.classList.toggle('zoomed');
            });
        }

        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var src = thumb.getAttribute('data-image');
                if (!src || !mainImage) {
                    return;
                }
                mainImage.setAttribute('src', src);
                mainWrap.classList.remove('zoomed');
                thumbs.forEach(function (t) { t.classList.remove('active'); });
                thumb.classList.add('active');
            });
        });
    });
})();
