jQuery(document).ready(function () {
    "use strict";

        // main slider 
        $(".main_slider").owlCarousel({
            items: 1,
            loop: true,
            dots: false,
            autoplay: true,
            nav: false,
            autoplayHoverPause: false,
            margin: 0,
            mouseDrag: true,
            smartSpeed: 1000,
            autoplayTimeout: 3000,

            navText: ["<i class='fa-solid fa-angle-left'></i>",
                "<i class='fa-solid fa-angle-right'></i>"
            ],
        });
    $('.select2').select2();

})
