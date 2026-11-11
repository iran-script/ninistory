@extends('admin.layout')
@section('title', 'مدیریت سفارش‌ها')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h1 class="text-3xl font-extrabold">سفارش‌ها</h1><span class="text-sm text-gray-500">{{ number_format($orders->total()) }} سفارش</span></div>
<form class="admin-card mb-4 admin-form" method="get">
    <label>جست‌وجو<input name="q" value="{{ request('q') }}" placeholder="شماره، نام یا تلفن"></label>
    <label>پرداخت<select name="payment_status"><option value="">همه</option>@foreach(['pending'=>'در انتظار پرداخت','paid'=>'پرداخت‌شده','failed'=>'ناموفق','expired'=>'منقضی','refunded'=>'بازپرداخت‌شده'] as $key=>$label)<option value="{{ $key }}" @selected(request('payment_status')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>پردازش<select name="fulfillment_status"><option value="">همه</option>@foreach(['awaiting_payment'=>'در انتظار پرداخت','ready_to_process'=>'آماده پردازش','processing'=>'در حال پردازش','packed'=>'بسته‌بندی‌شده','fulfilled'=>'تکمیل‌شده','cancelled'=>'لغوشده'] as $key=>$label)<option value="{{ $key }}" @selected(request('fulfillment_status')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>ارسال<select name="shipment_status"><option value="">همه</option>@foreach(['not_shipped'=>'ارسال نشده','shipped'=>'ارسال‌شده','in_transit'=>'در مسیر','delivered'=>'تحویل‌شده','failed_delivery'=>'مشکل در تحویل','returned_to_sender'=>'مرجوع به فرستنده'] as $key=>$label)<option value="{{ $key }}" @selected(request('shipment_status')===$key)>{{ $label }}</option>@endforeach</select></label>
    <label>از تاریخ<input type="date" name="from" value="{{ request('from') }}"></label><label>تا تاریخ<input type="date" name="to" value="{{ request('to') }}"></label>
    <label class="flex items-center gap-2"><input class="w-auto" type="checkbox" name="awaiting_action" value="1" @checked(request('awaiting_action'))> فقط نیازمند اقدام</label>
    <div class="admin-actions"><button class="admin-button">فیلتر</button><a class="admin-button admin-button--muted" href="{{ route('admin.orders.index') }}">پاک‌کردن</a></div>
</form>
<section class="admin-card admin-table-wrap"><table class="admin-table"><thead><tr><th>شماره / تاریخ</th><th>مشتری</th><th>مبلغ</th><th>پرداخت</th><th>پردازش</th><th>ارسال</th><th>اقلام</th><th></th></tr></thead><tbody>
@forelse($orders as $order)<tr><td><a class="font-bold" href="{{ route('admin.orders.show',$order) }}"><bdi dir="ltr">{{ $order->number }}</bdi></a><small class="block">{{ $order->created_at_jalali }}</small></td><td>{{ $order->recipient_name }}<br><small>{{ $order->phone }}</small></td><td>{{ number_format($order->total_toman) }} تومان</td><td>{{ $order->paymentLabel() }}</td><td>{{ $order->fulfillmentLabel() }}</td><td>{{ $order->shipmentLabel() }}</td><td>{{ $order->items_count }}</td><td><a class="admin-button" href="{{ route('admin.orders.show',$order) }}">جزئیات</a></td></tr>@empty<tr><td colspan="8">سفارشی پیدا نشد.</td></tr>@endforelse
</tbody></table>{{ $orders->links() }}</section>
@endsection
