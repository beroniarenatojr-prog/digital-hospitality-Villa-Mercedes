import './bootstrap';
import Alpine from 'alpinejs';
import flatpickr from 'flatpickr';
import Toastify from 'toastify-js';
import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

window.Alpine = Alpine;
window.flatpickr = flatpickr;
window.Toastify = Toastify;
window.Swiper = Swiper;
window.SwiperModules = { Navigation, Pagination };

Alpine.start();
