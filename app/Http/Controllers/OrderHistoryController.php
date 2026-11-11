<?php
namespace App\Http\Controllers;
use App\Models\Order;
use Illuminate\Http\Request;
class OrderHistoryController extends Controller {
 public function index(Request $r) { abort_unless($r->user(),403); $orders=$r->user()->orders()->withCount('items')->latest()->paginate(10); return view('store.orders.index',compact('orders')); }
 public function show(Request $r,Order $order) { abort_unless($r->user()?->id === $order->user_id,404); return view('store.orders.show',['order'=>$order->load(['items','shipments'])]); }
 public function guest(Request $r,string $token) { $order=Order::where('guest_access_token',$token)->with(['items','shipments'])->firstOrFail(); return view('store.orders.show',compact('order')); }
}
