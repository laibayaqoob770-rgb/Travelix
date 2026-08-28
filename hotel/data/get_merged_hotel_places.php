<?php
/**
 * HTTP JSON endpoint — merged static + admin-added + portal-onboarded hotel
 * places, called by client-side JS (hotels.php). The actual merge logic
 * lives in includes/hotel_places_lib.php so hotel/searched_hotel.php can
 * call it directly server-side instead of curl-ing this endpoint.
 */
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/travelix/includes/hotel_places_lib.php';

echo json_encode(hp_get_merged_hotel_places(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
