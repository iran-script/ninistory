@extends('layouts.store')
@section('title', 'سفارش ثبت شد | نینی‌استوری')
@section('content')<section class="mx-auto max-w-2xl px-4 py-16 text-center"><h1 class="text-3xl font-bold">سفارش ثبت شد</h1><p class="mt-4">شماره سفارش: <bdi dir="ltr">{{ $order->number }}</bdi></p><p class="mt-2">وضعیت پرداخت: در انتظار پرداخت؛ پرداختی انجام نشده است.</p><p class="mt-3 font-bold">مبلغ: {{ number_format($order->subtotal_toman) }} تومان</p></section>@endsection
