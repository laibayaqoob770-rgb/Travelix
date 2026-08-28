<?php
/** Server-side booking reservation gate. Serializes availability checks so two
 * simultaneous submissions cannot reserve the same final room. */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
$baseUrl='/travelix'; $docRoot=$_SERVER['DOCUMENT_ROOT'];
if(empty($_SESSION['user']['uid'])){http_response_code(401);echo json_encode(['success'=>false,'message'=>'Please log in again.']);exit;}
require_once $docRoot.$baseUrl.'/config/firebase_config.php';
require_once $docRoot.$baseUrl.'/includes/firestore_admin.php';
$sa=$docRoot.$baseUrl.'/config/firebase-service-account.json'; $project=FIREBASE_PROJECT_ID;
$input=json_decode(file_get_contents('php://input'),true)?:[]; $data=is_array($input['booking']??null)?$input['booking']:[];
$hotelId=trim((string)($data['hotelId']??'')); $roomType=trim((string)($data['roomType']??'')); $from=(string)($data['arrivalDate']??''); $to=(string)($data['departureDate']??''); $rooms=max(1,(int)($data['rooms']??1));
$proof=(string)($data['payment']['proofImagePath']??'');
if($hotelId===''||$from===''||$to===''||$proof===''||strpos($proof,'/travelix/payment_proofs/')!==0){echo json_encode(['success'=>false,'message'=>'Hotel, dates and payment proof are required.']);exit;}
$sessionUid=(string)$_SESSION['user']['uid'];
$sessionEmail=strtolower(trim((string)($_SESSION['user']['email']??'')));
$user=hp_firestore_get($sa,$project,'users/'.$sessionUid);

// Some older accounts have a duplicate Firestore profile for the same verified
// login email. In that case the browser sees the current Auth profile (with its
// refund details), while the legacy PHP session can still carry the older UID.
// Resolve the complete same-email profile instead of falsely rejecting a valid
// proof submission. The booking is attached to the resolved current profile so
// it remains visible in the browser user's Manage Bookings screen.
$hasRefundAccount=static function($profile){
  if(!is_array($profile)||empty($profile['paymentMethod'])||empty($profile['paymentAccountNumber']))return false;
  return strtolower((string)$profile['paymentMethod'])!=='bank'||!empty($profile['bankName']);
};
if(!$hasRefundAccount($user)&&$sessionEmail!==''){
  foreach(hp_firestore_query($sa,$project,'users','email',$sessionEmail) as $candidate){
    if($hasRefundAccount($candidate)){$user=$candidate;break;}
  }
}
if(!$hasRefundAccount($user)){echo json_encode(['success'=>false,'message'=>'Complete your refund account details before booking.']);exit;}
$bookingOwnerUid=(string)($user['id']??$sessionUid);
$hotel=hp_firestore_get($sa,$project,'hotels/'.$hotelId); if(!$hotel){echo json_encode(['success'=>false,'message'=>'Hotel not found.']);exit;}
$types=is_array($hotel['room_types']??null)?$hotel['room_types']:[]; $capacity=(int)($types[$roomType]['count']??($data['roomTypeCapacity']??0));
if($capacity<=0){echo json_encode(['success'=>false,'message'=>'Selected room type is unavailable.']);exit;}
$lockPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'travelix_booking_'.sha1($hotelId.'|'.$roomType).'.lock'; $lock=fopen($lockPath,'c');
if(!$lock||!flock($lock,LOCK_EX)){echo json_encode(['success'=>false,'message'=>'Could not reserve rooms. Please retry.']);exit;}
try{
  $booked=0; foreach(hp_firestore_query($sa,$project,'hotel_bookings','hotelId',$hotelId) as $b){$s=strtolower((string)($b['bookingStatus']??'pending'));if(in_array($s,['cancelled','rejected','payment_rejected'],true))continue;if($roomType!==''&&!empty($b['roomType'])&&(string)$b['roomType']!==$roomType)continue;if(!empty($b['arrivalDate'])&&!empty($b['departureDate'])&&(string)$b['arrivalDate']<$to&&$from<(string)$b['departureDate'])$booked+=(int)($b['rooms']??1);}
  if($booked+$rooms>$capacity){echo json_encode(['success'=>false,'message'=>'Only '.max(0,$capacity-$booked).' room(s) remain for these dates. Refresh and choose again.']);exit;}
  $data['uid']=$bookingOwnerUid;$data['userId']=$data['uid'];$data['userEmail']=(string)($_SESSION['user']['email']??'');
  $data['refundAccountSnapshot']=['method'=>(string)$user['paymentMethod'],'bankName'=>(string)($user['bankName']??''),'accountNumber'=>(string)$user['paymentAccountNumber']];
  $data['bookingStatus']='pending';$data['hotelPayoutStatus']='not_sent';$data['createdAt']=date('c');
  $id=hp_firestore_auto_id();
  $hotelName=(string)($data['hotelName']??'the hotel');
  $total=(float)($data['totalCharged']??$data['hotelPrice']??0);
  $hotelShare=(float)($data['hotelPrice']??0);
  $now=date('c');
  $writes=[
    ['path'=>'hotel_bookings/'.$id,'data'=>$data],
    ['path'=>'notifications/'.hp_firestore_auto_id(),'data'=>['userId'=>$bookingOwnerUid,'uid'=>$bookingOwnerUid,'title'=>'Booking Submitted','message'=>'Your '.$hotelName.' booking is awaiting payment verification from Travelix.','type'=>'hotel_booked','link'=>'/travelix/hotel/manage_bookings.php','isRead'=>false,'createdAt'=>$now]],
    ['path'=>'notifications/'.hp_firestore_auto_id(),'data'=>['audience'=>'hotel','hotelId'=>$hotelId,'title'=>'New Booking Request','message'=>'A guest requested '.$hotelName.'. Travelix is verifying payment and will send your PKR '.number_format($hotelShare).' share before confirmation.','type'=>'general','icon'=>'fa-solid fa-hotel','link'=>'/travelix/hotel_portal/hotel_bookings.php','isRead'=>false,'createdAt'=>$now]],
    ['path'=>'notifications/'.hp_firestore_auto_id(),'data'=>['audience'=>'admin','title'=>'New Booking Payment Awaiting Verification','message'=>(string)($_SESSION['user']['email']??'A guest').' paid PKR '.number_format($total).' for '.$hotelName.' — please verify the payment proof.','type'=>'general','icon'=>'fa-solid fa-file-invoice-dollar','link'=>'/travelix/admin_manage/booking_payments.php','isRead'=>false,'createdAt'=>$now]],
  ];
  if(!hp_firestore_commit($sa,$project,$writes)){echo json_encode(['success'=>false,'message'=>'Could not save the booking.']);exit;}
  echo json_encode(['success'=>true,'bookingId'=>$id]);
}finally{flock($lock,LOCK_UN);fclose($lock);}
