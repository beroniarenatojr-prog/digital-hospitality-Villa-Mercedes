# Routes — public guest site (Laravel `routes/web.php`)

Layout for all public pages below: `resources/views/layouts/customer.blade.php` (`<x-customer-layout>`).

| URL | Name | Controller | View | Summary |
|---|---|---|---|---|
| `/` | home | PageController@home | `resources/views/pages/home.blade.php` | Full-bleed hero photo, availability bar, three service cards, stats, featured rooms (lazy HTML), how-to-reserve, about teaser, gallery lightbox, FAQ, hours+map, closing CTA |
| `/rooms` | rooms | PageController@rooms | `resources/views/pages/facilities.blade.php` | Listing hero (cottages photo), availability bar, Rooms/Halls/Packages tabs, capacity+sort filters, facility cards |
| `/halls` | halls | PageController@halls | same facilities view | Same listing chrome; event-hosting hero |
| `/packages` | packages | PageController@packages | same facilities view | Same listing chrome; pool/grounds hero; cards often hide extra gallery images |
| `/gallery` | gallery | PageController@gallery | `resources/views/pages/gallery.blade.php` | `page-intro` band + photo grid + lightbox |
| `/about` | about | PageController@about | `resources/views/pages/about.blade.php` | Story, mission/vision |
| `/contact` | contact | PageController@contact | `resources/views/pages/contact.blade.php` | Contact details + form |
| `/faq` | faq.index | FaqController@index | FAQ list | |
| `/facilities/{facility}` | facilities.show | FacilityController@show | facility detail | |
| `/home/featured` | home.featured | PageController@featured | `pages/partials/featured-facilities.blade.php` | HTML fragment of three featured room cards |

Admin (`/admin/*`) and auth (`/login`, `/register`) use different layouts and are out of scope for this design.

Relevant router excerpt:

```php
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/home/featured', [PageController::class, 'featured'])->name('home.featured');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/gallery', [PageController::class, 'gallery'])->name('gallery');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/rooms', [PageController::class, 'rooms'])->name('rooms');
Route::get('/halls', [PageController::class, 'halls'])->name('halls');
Route::get('/packages', [PageController::class, 'packages'])->name('packages');
Route::get('/facilities/{facility}', [FacilityController::class, 'show'])->name('facilities.show');
```
