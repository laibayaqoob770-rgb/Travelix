<?php
/** Hotel confirms admin payout receipt and the room in one audited action. */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
$baseUrl='/travelix'; $docRoot=$_SERVER['DOCUMENT_ROOT'];
if (empty($_SESSION['hotel_staff'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Hotel login required.']); exit; }
require_once $docRoot.$baseUrl.'/config/firebase_config.php';
require_once $docRoot.$baseUrl.'/includes/firestore_admin.php';
require_once __DIR__.'/../includes/resolve_hotel.php';
$saPath=$docRoot.$baseUrl.'/config/firebase-service-account.json'; $projectId=FIREBASE_PROJECT_ID;
$hotel=hp_get_staff_hotel($saPath,$projectId,(string)($_SESSION['hotel_staff']['uid']??''));
$input=json_decode(file_get_contents('php://input'),true)?:[]; $bookingId=trim((string)($input['bookingId']??''));
$booking=$bookingId!==''?hp_firestore_get($saPath,$projectId,'hotel_bookings/'.$bookingId):null;
if (!$hotel || !$booking || (string)($booking['hotelId']??'')!==(string)($hotel['id']??'')) { echo json_encode(['success'=>false,'message'=>'Booking not found for your hotel.']); exit; }
if (strtolower((string)($booking['hotelPayoutStatus']??''))!=='sent' || empty($booking['hotelPayoutProof'])) { echo json_encode(['success'=>false,'message'=>'Travelix hotel payment and proof are required before confirmation.']); exit; }
$payoutId=(string)($booking['hotelPayoutId']??'');
$writes=[];
if ($payoutId!=='') $writes[]=['path'=>'payout_payments/'.$payoutId,'mask'=>true,'data'=>['status'=>'confirmed','confirmedAt'=>time(),'confirmedBy'=>(string)($_SESSION['hotel_staff']['email']??'')]];
$writes[]=['path'=>'hotel_bookings/'.$bookingId,'mask'=>true,'data'=>[
  'bookingStatus'=>'confirmed','hotelPayoutStatus'=>'confirmed_received','hotelPayoutConfirmedAt'=>date('c'),'roomConfirmedAt'=>date('c')
]];
$uid=(string)($booking['uid']??$booking['userId']??'');
if($uid!=='') $writes[]=['path'=>'notifications/'.hp_firestore_auto_id(),'data'=>['userId'=>$uid,'uid'=>$uid,'title'=>'Booking Confirmed','message'=>'The hotel received its payment and confirmed your room at '.(string)($booking['hotelName']??'the hotel').'.','type'=>'hotel_booking_confirmed','icon'=>'bi-building-check','link'=>'/travelix/hotel/manage_bookings.php','isRead'=>false,'createdAt'=>date('c')]];
$writes[]=['path'=>'notifications/'.hp_firestore_auto_id(),'data'=>['audience'=>'admin','title'=>'Hotel Confirmed Booking','message'=>(string)($booking['hotelName']??'Hotel').' confirmed payout receipt and reserved the room.','type'=>'payout_confirmed','icon'=>'fa-solid fa-circle-check','link'=>'/travelix/admin_manage/booking_payments.php','isRead'=>false,'createdAt'=>date('c')]];
if(!hp_firestore_commit($saPath,$projectId,$writes)){echo json_encode(['success'=>false,'message'=>'Could not confirm this booking.']);exit;}
echo json_encode(['success'=>true,'message'=>'Payment receipt and room reservation confirmed.']);
