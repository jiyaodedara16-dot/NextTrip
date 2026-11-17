-- ============================================================
-- hotels_restaurants_seed.sql
-- ------------------------------------------------------------
-- Adds "Nearby Hotels" and "Nearby Restaurants" data for the nine
-- destinations that currently have none (Taj Mahal, Goa Beaches,
-- Kerala Backwaters, Golden Temple, Jaipur, Varanasi, Red Fort,
-- Qutub Minar, Gateway of India — destination_id 21-29).
--
-- It also fixes a pre-existing gap: `restaurants` already had 3 rows
-- for Rann of Kutch (destination_id 30), but `destination_restaurants`
-- had no linking rows for them at all, so the Nearby Restaurants
-- section silently rendered empty there too. That's patched at the
-- bottom.
--
-- hotel_id / restaurant_id continue from the existing max (3),
-- so this is safe to run once against the current dump.
-- ============================================================

-- ------------------------------------------------------------
-- HOTELS (2 per destination, coordinates near each destination)
-- ------------------------------------------------------------
INSERT INTO `hotels`
  (`hotel_id`, `destination_id`, `hotel_name`, `address`, `latitude`, `longitude`, `price_per_night`, `rating`, `image_path`, `status`)
VALUES
  (4,  21, 'The Oberoi Amarvilas',      'Taj East Gate Road, Agra',                27.1735000, 78.0424000, 18500.00, 4.9, 'assets/images/hotels/oberoi-amarvilas.jpg',   'Active'),
  (5,  21, 'Hotel Taj Resorts',         'Fatehabad Road, Agra',                    27.1690000, 78.0490000,  3200.00, 4.2, 'assets/images/hotels/taj-resorts.jpg',        'Active'),

  (6,  22, 'Taj Fort Aguada Resort & Spa', 'Sinquerim, Candolim, North Goa',       15.4970000, 73.7710000, 12500.00, 4.7, 'assets/images/hotels/taj-fort-aguada.jpg',    'Active'),
  (7,  22, 'Cavala Beach Resort',       'Baga, North Goa',                         15.5560000, 73.7520000,  4200.00, 4.3, 'assets/images/hotels/cavala-beach-resort.jpg','Active'),

  (8,  23, 'Punnamada Resort',          'Punnamada, Alappuzha, Kerala',             9.5140000, 76.3400000,  6500.00, 4.5, 'assets/images/hotels/punnamada-resort.jpg',   'Active'),
  (9,  23, 'Lake Palace Resort',        'Kumarakom, Kottayam, Kerala',              9.6180000, 76.4290000,  8200.00, 4.6, 'assets/images/hotels/lake-palace-resort.jpg', 'Active'),

  (10, 24, 'Hyatt Regency Amritsar',    'Ranjit Avenue, Amritsar',                 31.6410000, 74.8560000,  7500.00, 4.5, 'assets/images/hotels/hyatt-regency-asr.jpg',  'Active'),
  (11, 24, 'Hotel Golden Tower',        'Near Golden Temple, Amritsar',            31.6205000, 74.8790000,  3100.00, 4.2, 'assets/images/hotels/golden-tower.jpg',       'Active'),

  (12, 25, 'Rambagh Palace',            'Bhawani Singh Road, Jaipur',              26.8990000, 75.8090000, 22000.00, 4.9, 'assets/images/hotels/rambagh-palace.jpg',     'Active'),
  (13, 25, 'Hotel Pearl Palace',        'Hathi Bhata, Jaipur',                     26.9150000, 75.8210000,  2800.00, 4.4, 'assets/images/hotels/pearl-palace.jpg',       'Active'),

  (14, 26, 'BrijRama Palace',           'Munshi Ghat, Varanasi',                  25.3080000, 83.0090000,  9500.00, 4.6, 'assets/images/hotels/brijrama-palace.jpg',    'Active'),
  (15, 26, 'Hotel Ganges View',         'Assi Ghat, Varanasi',                    25.2830000, 83.0060000,  3400.00, 4.4, 'assets/images/hotels/ganges-view.jpg',        'Active'),

  (16, 27, 'The Lalit New Delhi',       'Barakhamba Avenue, Connaught Place, Delhi', 28.6270000, 77.2230000, 8500.00, 4.5, 'assets/images/hotels/the-lalit-delhi.jpg',  'Active'),
  (17, 27, 'Hotel Broadway',            'Asaf Ali Road, Delhi',                   28.6470000, 77.2360000,  3200.00, 4.1, 'assets/images/hotels/hotel-broadway.jpg',     'Active'),

  (18, 28, 'The Suryaa New Delhi',      'New Friends Colony, Delhi',              28.5620000, 77.2720000,  7200.00, 4.4, 'assets/images/hotels/the-suryaa.jpg',         'Active'),
  (19, 28, 'Roseate House',             'Aerocity, Delhi',                        28.5470000, 77.1210000,  9800.00, 4.6, 'assets/images/hotels/roseate-house.jpg',      'Active'),

  (20, 29, 'The Taj Mahal Palace',      'Apollo Bandar, Colaba, Mumbai',           18.9217000, 72.8332000, 25000.00, 4.9, 'assets/images/hotels/taj-mahal-palace.jpg',   'Active'),
  (21, 29, 'Hotel Suba Palace',         'Battery Street, Colaba, Mumbai',          18.9225000, 72.8365000,  4200.00, 4.1, 'assets/images/hotels/suba-palace.jpg',        'Active');

INSERT INTO `destination_hotels` (`destination_id`, `hotel_id`) VALUES
  (21, 4), (21, 5),
  (22, 6), (22, 7),
  (23, 8), (23, 9),
  (24, 10), (24, 11),
  (25, 12), (25, 13),
  (26, 14), (26, 15),
  (27, 16), (27, 17),
  (28, 18), (28, 19),
  (29, 20), (29, 21);

-- ------------------------------------------------------------
-- RESTAURANTS (2 per destination)
-- ------------------------------------------------------------
INSERT INTO `restaurants`
  (`restaurant_id`, `destination_id`, `restaurant_name`, `cuisine`, `address`, `latitude`, `longitude`, `rating`, `image_path`, `status`)
VALUES
  (4,  21, 'Pinch of Spice',    'North Indian',           'Fatehabad Road, Agra',              27.1705000, 78.0455000, 4.5, 'assets/images/restaurants/pinch-of-spice.jpg',   'Active'),
  (5,  21, 'Esphahan',          'Mughlai',                'Taj East Gate Road, Agra',          27.1732000, 78.0420000, 4.8, 'assets/images/restaurants/esphahan.jpg',         'Active'),

  (6,  22, "Fisherman's Wharf", 'Goan Seafood',           'Cavelossim, South Goa',             15.1770000, 73.9430000, 4.6, 'assets/images/restaurants/fishermans-wharf.jpg', 'Active'),
  (7,  22, "Britto's",          'Goan',                   'Baga Beach, North Goa',             15.5555000, 73.7515000, 4.4, 'assets/images/restaurants/brittos.jpg',          'Active'),

  (8,  23, 'Vembanad Grill',    'Kerala',                 'Alappuzha, Kerala',                  9.4990000, 76.3410000, 4.3, 'assets/images/restaurants/vembanad-grill.jpg',   'Active'),
  (9,  23, 'Chakara Restaurant','South Indian',           'Alappuzha, Kerala',                  9.4950000, 76.3350000, 4.4, 'assets/images/restaurants/chakara.jpg',          'Active'),

  (10, 24, 'Kesar Da Dhaba',    'Punjabi',                'Chowk Passian, Amritsar',           31.6250000, 74.8730000, 4.6, 'assets/images/restaurants/kesar-da-dhaba.jpg',   'Active'),
  (11, 24, "Brothers' Dhaba",   'Punjabi',                'Lawrence Road, Amritsar',           31.6330000, 74.8620000, 4.5, 'assets/images/restaurants/brothers-dhaba.jpg',   'Active'),

  (12, 25, 'Chokhi Dhani',      'Rajasthani',             'Tonk Road, Jaipur',                 26.7860000, 75.8330000, 4.5, 'assets/images/restaurants/chokhi-dhani.jpg',     'Active'),
  (13, 25, 'Suvarna Mahal',     'Royal Rajasthani',       'Rambagh Palace, Jaipur',            26.8990000, 75.8090000, 4.7, 'assets/images/restaurants/suvarna-mahal.jpg',    'Active'),

  (14, 26, 'Kashi Chat Bhandar','Street Food',            'Godowlia, Varanasi',                25.3100000, 83.0040000, 4.5, 'assets/images/restaurants/kashi-chat-bhandar.jpg','Active'),
  (15, 26, 'Baati Chokha',      'Bihari-UP',              'Assi Ghat, Varanasi',               25.2840000, 83.0050000, 4.3, 'assets/images/restaurants/baati-chokha.jpg',     'Active'),

  (16, 27, "Karim's",           'Mughlai',                'Jama Masjid, Old Delhi',            28.6505000, 77.2335000, 4.4, 'assets/images/restaurants/karims.jpg',           'Active'),
  (17, 27, 'Chor Bizarre',      'Kashmiri',               'Asaf Ali Road, Delhi',              28.6460000, 77.2350000, 4.3, 'assets/images/restaurants/chor-bizarre.jpg',     'Active'),

  (18, 28, 'Saravana Bhavan',   'South Indian',           'Green Park, Delhi',                 28.5590000, 77.2060000, 4.3, 'assets/images/restaurants/saravana-bhavan.jpg',  'Active'),
  (19, 28, 'Qutub Kitchen',     'Multi-cuisine',          'Mehrauli, Delhi',                   28.5260000, 77.1880000, 4.2, 'assets/images/restaurants/qutub-kitchen.jpg',    'Active'),

  (20, 29, 'Leopold Cafe',      'Continental',            'Colaba Causeway, Mumbai',           18.9224000, 72.8313000, 4.2, 'assets/images/restaurants/leopold-cafe.jpg',     'Active'),
  (21, 29, 'Bademiya',          'Mughlai Street Food',    'Tulloch Road, Colaba, Mumbai',      18.9210000, 72.8330000, 4.4, 'assets/images/restaurants/bademiya.jpg',         'Active');

INSERT INTO `destination_restaurants` (`destination_id`, `restaurant_id`) VALUES
  (21, 4), (21, 5),
  (22, 6), (22, 7),
  (23, 8), (23, 9),
  (24, 10), (24, 11),
  (25, 12), (25, 13),
  (26, 14), (26, 15),
  (27, 16), (27, 17),
  (28, 18), (28, 19),
  (29, 20), (29, 21),

  -- Fix: Rann of Kutch (30) already had 3 restaurant rows but no
  -- destination_restaurants links, so nothing ever showed for it.
  (30, 1), (30, 2), (30, 3);
