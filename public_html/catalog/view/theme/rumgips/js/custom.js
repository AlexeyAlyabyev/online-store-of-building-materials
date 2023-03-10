$(function(){

	// Взаимодействия с нижней панелькой мобильного меню
	$(".mobile_menu .navigation .catalog_opener, .mobile_menu .navigation .search").click(function(){

		if (window.pageYOffset > 0) $("header").toggleClass("sticky");

		if ($(".mobile_menu .catalog").hasClass("active") && $(this).siblings().hasClass("active")) {

			if ($(this).hasClass("catalog_opener")) {
				$(".mobile_menu .navigation .item").removeClass("active");
				$("body").toggleClass("no_scroll");
				$(".mobile_menu .catalog").toggleClass("active");
			}
			if ($(this).hasClass("search")) {
				$(".mobile_menu .navigation .item").removeClass("active");
				$(this).addClass("active");
				$(".mobile_menu .catalog .search input").focus();
			}
		} else {
			$(this).toggleClass('active');
			$("body").toggleClass("no_scroll");
			$(".mobile_menu .catalog").toggleClass("active");
		}

		if ($(this).hasClass("search") && $(".mobile_menu .catalog").hasClass("active"))
			$(".mobile_menu .catalog .search input").focus();
	});

	// Переключение разделов в мобильном меню
	$(".mobile_menu .catalog .switcher li").click(function(){
		$(this).siblings().removeClass("active");
		$(this).addClass("active");
		$(this).parent().next().children().removeClass("active");
		$(this).parent().next().children().eq($(this).index()).addClass("active");
	});

	resizeMobileCatalog();

  $(".desktop_menu .search_and_cart .search button").click(function(){
		$(".desktop_menu .menu").toggleClass("hidden");
		$(".desktop_menu .search_and_cart .search input").toggleClass("active");
		$(".desktop_menu .search_and_cart").toggleClass("active");
		$(".desktop_menu .search_and_cart .search input").focus();
	});

  $(document).click(function(e){
    if (!$(e.target).parents(".search").length && !$(e.target).hasClass("search")) {
      	$(".desktop_menu .search_and_cart").removeClass("active");
        $(".desktop_menu .search_and_cart .search input").removeClass("active");
        $(".desktop_menu .menu").removeClass("hidden");
        $(document).focus();
    }
  });

	// Доступность кнопки отправить после подтверждения с ознакомлением политики конфиденциальности
	$(".modal .body input[type='checkbox']").change(function(){
		if ($(this).prop("checked"))
			$(this).parent().siblings("input[type='submit']").prop("disabled", false);
		else
			$(this).parent().siblings("input[type='submit']").prop("disabled", true);
	});

	$(".modal").click(function(e){
		if ($(this)[0] == e.target) closeModal();
	});

	$(".complaint-modal").click(function(e){
		if ($(this)[0] == e.target) closeComplaintModal();
	});

	// Переключение сортировки товаров на мобилке
	$(".products_category .header .sort_block .sort button").on("click touch", function(){
		$(this).next().toggleClass("active");
	});

	$(".products_category .header .info >button").click(function(){
		window.location = '/search/?description=true&search=' + $(this).prev().val();
	});

  $('input[name^="quantity"]').on('input', function() {
    if ($(this).val() >=1 ) {
      cartUpdate();
      }
    }
  )

  $('input[name^="quantity"]').on('change', function() {
      if ($(this).val() == 0) {
        cartUpdate();
        $(this).parents(".checkout-cart__item").remove();
      }
    }
  )

	$(".quantity_block .minus").click(function(){
    if ($(this).parent().hasClass('quantity_block__cart')){
      $(this).siblings("#input-quantity").val(+$(this).siblings("#input-quantity").val() - 1);

      cartUpdate();

      if ($(this).siblings("#input-quantity").val() === '0') {
        $(this).parents(".checkout-cart__item").remove();
      }
    }
    else if (+$(this).siblings("#input-quantity").val() > 1)
			$(this).siblings("#input-quantity").val(+$(this).siblings("#input-quantity").val() - 1);
	});
	$(".quantity_block .plus").click(function(){
    if ($(this).parent().hasClass('quantity_block__cart')){
			$(this).siblings("#input-quantity").val(+$(this).siblings("#input-quantity").val() + 1);
      cartUpdate();
    }
    else if (+$(this).siblings("#input-quantity").val() < 999)
			$(this).siblings("#input-quantity").val(+$(this).siblings("#input-quantity").val() + 1);
	});

  $("input[name='shipping_method']").on("change", calculateDelivery);
  calculateDelivery();

	$(".product_card .info .options .option_images label").click(function(){
		$(".product_card .info .options .option_images label").removeClass("active");
		$(this).addClass("active");
		if ($(".product_card .header .product_images_slider .swiper-slide").length - 1 > 0){
			$(".product_card .header .product_images_slider .swiper-slide[aria-label^='1 / '] img").attr("src", $(this).find("img").attr("data-full-size"));
			$(".product_card .header .product_images_slider_thumbs .swiper-slide[aria-label^='1 / ']").attr("src", $(this).find("img").attr("data-full-size"));
		}
		else
			$(".product_card .header .product_images_slider .swiper-slide img").attr("src", $(this).find("img").attr("data-full-size"));
		if ($(".product_card .info .price_and_controls .price span").length){
			$(".product_card .info .price_and_controls .price span").html($(this).find("input").attr("data-price"));
			$(".product_card .info .price_and_controls .price p").html($(this).find("input").attr("data-special"));
		} else
			$(".product_card .info .price_and_controls .price p").html($(this).find("input").attr("data-price"));
	});
});

// "Показать еще" в категориях, производителях и т.д.
// Готовый компонент, можно вставлять в любой проект opencart (Предварительно заменив .products_list и .item на классы в проекте)
var query_in_process = false;

if ($(".pagination").length){
	$('<button class="show_more">Показать еще</button>').insertBefore(".pagination");

	$(document).scroll(function(){
		if ($(".show_more").length && $(".show_more").position().top - window.scrollY < window.innerHeight/2 && !query_in_process && sessionStorage.getItem("dynamic_loading")) {
			loadNextPage();
		}
	});

	$(".show_more").click(function(){
		sessionStorage.setItem("dynamic_loading", true);
		loadNextPage();
	});
}

function loadNextPage(){
	let query_url = new URL(window.location.href);

	if (query_url.searchParams.get("page") == null) {
		query_url.searchParams.set("page", 1);
	}
	query_url.searchParams.set("page", +query_url.searchParams.get("page") + 1);

	$.ajax({
		url: query_url,
		dataType: 'html',
		beforeSend: function(){
			$(".show_more").text("Загрузка...");
			query_in_process = true;
		},
		complete: function() {
			$(".show_more").text("Показать еще");
			query_in_process = false;
		},
		success: function(html) {
			if ($(html).find(".products_list .item").length != 0) {

				$(".products_list").append($(html).find(".products_list .item"));
				$(".pagination").replaceWith($(html).find(".pagination"));

				window.history.pushState({}, '', query_url);

			} else {
				sessionStorage.setItem("dynamic_loading", false);
				$(".show_more").remove();
			}
		}
	});
}

function cartUpdate(){
  $.ajax({
    url: 'index.php?route=checkout/cart/editCart',
    type: 'post',
    data: $(".checkout-cart input[type=\'number\']"),
    dataType: 'json',
    success: function(json) {
      if (json['success']) {
				console.log(json);
        setTimeout(function () {
          $(".mobile_menu .navigation .item.cart .quantity").html(json['total_short']);
          $("header .body .main_cart span").html(json['total_short']);

          $(".checkout-cart__total-count").html(json["total_short_text"]);

          $(".checkout-cart__sub-total").html(json["initial_total"] + ' ₽');
          $(".checkout-cart__total-discount").html(json["discount"] + ' ₽');
          $(".checkout-cart__total-with-discount").html(json["total"] + ' ₽');

          for (key in json['products']) {
            $("input[name='quantity["+ key +"]']").parent().next().html(json['products'][key]['total'] + ' ₽');
            $("input[name='quantity["+ key +"]']").parent().siblings(".checkout-cart__discount").html(json['products'][key]['discount'] + ' ₽');
          }

          if (json["total"] === 0) {
            location.reload();
          }

        }, 100);
        $('#cart > ul').load('index.php?route=common/cart/info ul li');
      }
    }
  });
}

function calculateDelivery(){
  let delivery = $("input[name='shipping_method']:checked").attr("data-delivery-price");
  let total_with_delivery = $("input[name='shipping_method']:checked").attr("data-total");
  $(".checkout-cart__delivery span").html(delivery + ' ₽');
  $(".order-registration__delivery-cost").html(delivery + ' ₽');

  $(".checkout-cart__total-value.checkout-cart__total-bold span").html(total_with_delivery);
}


// Слайдер брендов на главной
const brands_slider = new Swiper('.brands_slider .wrapper', {
	loop: true,
	slidesPerView: 1,
	spaceBetween: 15,

	breakpoints: {
		768: {
			slidesPerView: 2,
			spaceBetween: 10
		},
		992: {
      slidesPerView: 'auto',
      loop: true,
      speed: 5000,
      slidesPerView: '4',
      autoplay: {
        enabled: true,
        delay: 1,
      },
      pagination: false,
		},
	},

	pagination: {
		el: '.brands_slider .swiper-pagination',
		clickable: true,
	},
});


// Мини - Слайдер на странице возврата и обмена товаров
const exchange_swiper = new Swiper('.exchange-swiper', {
	slidesPerView: "auto",
	spaceBetween: 36,

	breakpoints: {
		768: {
			slidesPerView: 3,
			spaceBetween: 130
		},
	},
});


// Слайдер фото товара на странице карточки товара
if ($(".product_images_slider .swiper-wrapper").children().length - 1){
	const product_images_slider_thumbs = new Swiper('.product_images_slider_thumbs', {
		slidesPerView: "auto",
		spaceBetween: 10,

	});

	const product_images_slider = new Swiper('.product_images_slider', {
		loop: true,
		slidesPerView: 1,
		navigation: {
			nextEl: '.product_images_slider .next',
			prevEl: '.product_images_slider .prev',
		},

		thumbs: {
			swiper: {
				el: '.product_images_slider_thumbs',
				slidesPerView: 'auto',
				spaceBetween: 10,
			}
		  }
	});
}

//Слайдер навигации на странице рекламации
if (window.innerWidth < 768) {
	const navigation_swiper = new Swiper('.navigation-swiper', {
		slidesPerView: 'auto',
		spaceBetween: 36,
	});
}

// // Слайдер формы рекламации
const complaint_swiper = new Swiper('.complaint-swiper', {
	spaceBetween: 20,
	allowTouchMove: false,
	autoHeight: true,
	navigation: {
		nextEl: '.complaint-swiper__button-next',
		prevEl: '.complaint-swiper__button-prev',
	},

	thumbs: {
		swiper: {
			el: '.navigation-swiper',
			slidesPerView: 'auto',
			spaceBetween: 36,
		}
	  }
});


// Изменение размера мобильной менюшки в зависимости от наличия верхнего рекламного баннера
function resizeMobileCatalog(){
	if (window.innerWidth >= 768 && window.innerWidth < 992)
		if ($(".new_gips").length){
			$(".mobile_menu .catalog").css("top", $(".new_gips")[0].offsetHeight + $("header")[0].offsetHeight + 1);
			$(".mobile_menu .catalog .body").css("margin-bottom", $(".new_gips")[0].offsetHeight + $("header")[0].offsetHeight + $(".mobile_menu .navigation")[0].offsetHeight);
		}
		else {
			$(".mobile_menu .catalog").css("top", $("header")[0].offsetHeight + 1);
			$(".mobile_menu .catalog .body").css("margin-bottom", $("header")[0].offsetHeight + $(".mobile_menu .navigation")[0].offsetHeight);
		}
}

// Затемнение для десктопного меню
function showBlackout(){
	$(".blackout").addClass("active");
	setTimeout(function(){
		$(".blackout").addClass("shadow");
	},10);
}
function hideBlackout(){
	$(".blackout").removeClass("shadow");
	setTimeout(function(){
		$(".blackout").removeClass("active");
	},290);
}

// Появление и скрытие модалки обратного звонка
function showModal(){
	$(".modal").addClass("active");
	$("body").addClass("no_scroll");
	setTimeout(function(){
		$(".modal").addClass("visible");
	},10);
}
function closeModal(){
	$("body").removeClass("no_scroll");
	$(".modal").removeClass("visible");
	setTimeout(function(){
		$(".modal").removeClass("active");
	},310);
}

function showMenu(){
  $(".desktop_menu .catalog .content").toggleClass("active");
}

// Загрузка чеков и гарантийного талона на странице рекламации
const cheques = document.querySelectorAll('.complaint-form__add');
cheques.forEach((cheque) => {
	const label = cheque.nextElementSibling;
	const labelOld = label.innerHTML;
	const deleteButton = label.nextElementSibling;

	cheque.addEventListener('change', (evt) => {
		let fileName = '';
		fileName = evt.target.value.split('\\').pop();

		if ( fileName ) {
			label.innerHTML = fileName;
			deleteButton.style.display = 'block';
		}
		else {
			label.innerHTML = labelOld;
			deleteButton.style.display = 'none';
		}

		deleteButton.addEventListener('click', () => {
			cheque.value = '';
			label.innerHTML = labelOld;
			deleteButton.style.display = 'none';
		})
	});
});


// прокрутка страницы в начало
const scrollUp = () => {
	if (window.innerWidth < 768) {
		window.scrollTo({
			top: 66,
			behavior: "smooth",
		  });
	}
	if (window.innerWidth >= 768 && window.innerWidth < 992) {
		window.scrollTo({
			top: 161,
			behavior: "smooth",
		  });
	}
	if (window.innerWidth >= 992) {
		window.scrollTo({
			top: 212,
			behavior: "smooth",
		  });
	}
}


// показ и скрытие модалки рекламации
function showComplaintModal(){
	$(".complaint-modal").addClass("complaint-modal--active");
	$("body").addClass("no_scroll");
	setTimeout(function(){
		$(".complaint-modal").addClass("complaint-modal--visible");
	},10);
}
function closeComplaintModal(){
	$("body").removeClass("no_scroll");
	$(".complaint-modal").removeClass("complaint-modal--visible");
	setTimeout(function(){
		$(".complaint-modal").removeClass("complaint-modal--active");
		window.location = "/";
	},310);
}

$(document).on('keyup', function(e) {
	if ( e.key == "Escape" ) {
		$("body").removeClass("no_scroll");
		$(".complaint-modal").removeClass("complaint-modal--visible");
	    setTimeout(function(){
		$(".complaint-modal").removeClass("complaint-modal--active");
	},310);
	}
});


// Проверка валидности первого слайда рекламации
const checkValidComplaintSlide1 = () => {

	const naturalRadio = document.getElementById('natural');
	const legalRadio = document.getElementById('legal');
	let slideIsValid = true;

	if (naturalRadio.checked) {
		const inputs = document.querySelectorAll('.slide-1');
		inputs.forEach((input) => {
			input.classList.remove('complaint-form__input--error');
		   if (!input.validity.valid) {
			   input.classList.add('complaint-form__input--error');
			   slideIsValid = false;
		   }
	   })
	}

	if (legalRadio.checked) {
		const inputs = document.querySelectorAll('.slide-1-2');
		inputs.forEach((input) => {
			input.classList.remove('complaint-form__input--error');
		   if (!input.validity.valid) {
			   input.classList.add('complaint-form__input--error');
			   slideIsValid = false;
		   }
	   })
	}

	const argee = document.getElementById("agree");
	if (!argee.checked) {
		argee.classList.add('complaint-form__input--error');
		slideIsValid = false;
	}

	slideIsValid ?
		complaint_swiper.activeIndex = complaint_swiper.activeIndex :
		complaint_swiper.activeIndex = complaint_swiper.activeIndex -1;
}


// Проверка валидности второго слайда рекламации
const checkValidComplaintSlide2 = () => {
	let slideIsValid = false;
	const checkReasons = document.querySelectorAll('.complaint-form__reason');

	checkReasons.forEach((reason) => {
	   if (reason.checked) {
		   slideIsValid = true;
	   }
   })

   slideIsValid ?
		complaint_swiper.activeIndex = complaint_swiper.activeIndex :
		complaint_swiper.activeIndex = complaint_swiper.activeIndex -1;
}

// Проверка валидности третьего слайда рекламации
const checkValidComplaintSlide3 = () => {
	let slideIsValid = true;
	const inputs = document.querySelectorAll('.slide-3');
	inputs.forEach((input) => {
		input.classList.remove('complaint-form__input--error');
		if (!input.validity.valid) {
			input.classList.add('complaint-form__input--error');
			slideIsValid = false;
		}
	})
	slideIsValid ?
		complaint_swiper.activeIndex = complaint_swiper.activeIndex :
		complaint_swiper.activeIndex = complaint_swiper.activeIndex -1;
}

// Проверка валидности четвертого слайда рекламации
const checkValidComplaintSlide4 = () => {
	let slideIsValid = true;
	const inputs = document.querySelectorAll('.slide-4');
	inputs.forEach((input) => {
		input.classList.remove('complaint-form__input--error');
		if (!input.validity.valid) {
			input.classList.add('complaint-form__input--error');
			slideIsValid = false;
		}
	})
	slideIsValid ?
		// complaint_swiper.activeIndex =
		slideIsValid = true
		//отправка данных на сервер !!!
		:
		complaint_swiper.activeIndex = complaint_swiper.activeIndex -1;
}



// удаление нового товара на третьем слайде рекламации
const deleteProduct = (newProduct) => {
	const removeBtn = newProduct.querySelector('.complaint-form__delete-article-button');
	removeBtn.addEventListener('click', function () {
	    newProduct.remove();
		complaint_swiper.updateAutoHeight();
	});
}


let countOfProducts = 2; // счетчик добавляемых товаров
// добавление блока нового товара на третий слайд рекламации
const addProduct = () => {
	countOfProducts++;
	const similarAdTemplate = document.querySelector('#product').content.querySelector('.complaint-form__article-container');
	const newProduct = similarAdTemplate.cloneNode(true);
	document.querySelector(".complaint-form__article-list").appendChild(newProduct);

	deleteProduct(newProduct); // обработчик на удаление нового товара
	// initBtnQuantity(newProduct); // работа кнопок количества товара
	addAttributes(newProduct, countOfProducts); // атрибуты для полей
	complaint_swiper.updateAutoHeight();
}

// атрибуты для новых полей товара
const addAttributes = (newProduct, i) => {
	const articleLabel = newProduct.querySelector('.complaint-form__article-label');
	articleLabel.setAttribute('for', 'article-' + i );
	const articleInput = newProduct.querySelector('.complaint-form__article-input');
	articleInput.id = 'article-' + i;
    articleInput.setAttribute('name', 'product-' + i + '-article' );

	const productNameLabel = newProduct.querySelector('.complaint-form__product-name-label');
	productNameLabel.setAttribute('for', 'product-name-' + i );
	const productNameInput = newProduct.querySelector('.complaint-form__product-name-input');
	productNameInput.id = 'product-name-' + i;
    productNameInput.setAttribute('name', 'product-' + i + '-name' );

	const quantityLabel = newProduct.querySelector('.complaint-form__quantity-label');
	quantityLabel.setAttribute('for', 'quantity-' + i );
	const quantityInput = newProduct.querySelector('.complaint-form__quantity-input');
	quantityInput.id = 'quantity-' + i;
    quantityInput.setAttribute('name', 'product-' + i + '-quantity' );

	const productCostLabel = newProduct.querySelector('.complaint-form__product-cost-label');
	productCostLabel.setAttribute('for', 'product-cost-' + i );
	const productCostInput = newProduct.querySelector('.complaint-form__product-cost-input');
	productCostInput.id = 'product-cost-' + i;
  productCostInput.setAttribute('name', 'product-' + i + '-cost' );
}

//Слайдер навигации на странице оформления заказа
if (window.innerWidth < 768) {
	const order_swiper = new Swiper('.order-swiper', {
		slidesPerView: 'auto',
		spaceBetween: 36,
	});
}

// Слайдер формы оформления заказа
const registration_swiper = new Swiper('.registration-swiper', {
	spaceBetween: 20,
	allowTouchMove: false,
	autoHeight: true,
	navigation: {
		nextEl: '.registration-swiper__button-next',
		prevEl: '.registration-swiper__button-prev',
	},

	thumbs: {
		swiper: {
			el: '.order-swiper',
			slidesPerView: 'auto',
			spaceBetween: 36,
		}
	}
});

// Кнопка наверх
window.addEventListener('scroll', () => {
  const upButton = document.querySelector('.scroll-up');
  if (window.pageYOffset > 700) {
    upButton.classList.add('scroll-up--show');
  } else {
    upButton.classList.remove('scroll-up--show')
  }
});
let buttonUp = document.querySelector('.scroll-up');
function backUp() {
  if (window.pageYOffset > 0) {
    window.scrollBy(0, -80);
    setTimeout(backUp, 10);
  }
};
buttonUp.addEventListener('click', backUp);

// Слайдер "С этим товаром покупают"
const recommended_products_swiper = new Swiper('.recommended-products-swiper', {
	slidesPerView: 'auto',
	spaceBetween: 10,

	breakpoints: {
		768: {
			slidesPerView: 3,
			spaceBetween: 15
		},
		992: {
			slidesPerView: 4,
			spaceBetween: 20,
		},
	},

	pagination: {
		el: '.recommended-products-swiper__pagination',
		clickable: true,
	},

  navigation: {
    nextEl: '.recommended-products-swiper-button-next',
    prevEl: '.recommended-products-swiper-button-prev',
  },
});

// Слайдер "Хит продаж"
const productsHitSwiper = new Swiper('.products-hit-swiper', {
	slidesPerView: 'auto',
	spaceBetween: 10,

	breakpoints: {
		768: {
			slidesPerView: 3,
			spaceBetween: 15
		},
		992: {
			slidesPerView: 4,
			spaceBetween: 20,
		},
	},

	pagination: {
		el: '.products-hit-swiper__pagination',
		clickable: true,
	},

  navigation: {
    nextEl: '.recommended-products-swiper-button-next',
    prevEl: '.recommended-products-swiper-button-prev',
  },
});

function showProductAdditionalImage(area){
	$(area).parents(".images").find("img").removeClass("active");
	$(area).parents(".images").find("img").eq($(area).index()).addClass("active");
}

function hideProductAdditionalImages(all_areas){
	$(all_areas).parents(".images").find("img").removeClass("active");
}

// Зум фото в карточке товара
var zoomSwiper;

function zoomIn(img){
	$(".zoom .swiper").remove();
	$("<div class='swiper'><div class='swiper-wrapper'></div></div>").appendTo(".zoom");
	$(".product_card .header .product_images_slider .thumbnail:not(.swiper-slide-duplicate)").clone().appendTo(".zoom .swiper .swiper-wrapper");
	
	$(".zoom .swiper .zoom_pagination, .zoom .swiper .prev, .zoom .swiper .next").remove();
	$("<div class='zoom_pagination'></div>").appendTo(".zoom .swiper");
	$("<div class='prev'></div>").appendTo(".zoom .swiper");
	$("<div class='next'></div>").appendTo(".zoom .swiper");

	zoomSwiper = new Swiper('.zoom .swiper', {
		loop: true,

		pagination: {
			el: '.zoom .swiper .zoom_pagination',
			clickable: true,
		},
	
		navigation: {
			nextEl: '.zoom .swiper .next',
			prevEl: '.zoom .swiper .prev',
		},
	});

	if (window.innerWidth >= 992) {
		let window_min_side = window.innerHeight < window.innerWidth ? window.innerHeight : window.innerWidth;
		window_min_side -= 120;
		$(".product_card .header .zoom .swiper .swiper-wrapper img").css("width", window_min_side + "px");
	}
	zoomSwiper.slideTo(+$(img).attr("data-swiper-slide-index") + 1, 0);

	$(".product_card .header .zoom").addClass("active");
	$(".product_card .header .zoom").addClass("visible");
	$("body").addClass("no_scroll");
}

function zoomOut(){
	$(".product_card .header .zoom").removeClass("visible");
	setTimeout(function(){
		$(".product_card .header .zoom").removeClass("active");
		$("body").removeClass("no_scroll");
	}, 300);
}

$(".product_card .header .product_images_slider .thumbnail").click(function(){
	zoomIn(this);
});
$(".product_card .header .zoom").click(function(e){
	e.target == $(".product_card .header .zoom")[0] ? zoomOut() : "";
});
$(document).keydown(function(e){
	if (e.key == "Escape") zoomOut();
	if ($(".product_card .header .zoom").hasClass("visible")){
		if (e.key == "ArrowRight") zoomSwiper.slideNext();
		if (e.key == "ArrowLeft") zoomSwiper.slidePrev();
	}
});
$(".product_card .header .zoom button.close").click(zoomOut);