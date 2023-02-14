@extends('layouts.core.backend', [
    'menu' => 'payment',
])

@section('title', trans('messages.payment_gateways'))

@section('page_header')

    <div class="page-title">
        <ul class="breadcrumb breadcrumb-caret position-right">
            <li class="breadcrumb-item"><a href="{{ action("Admin\HomeController@index") }}">{{ trans('messages.home') }}</a></li>
        </ul>
        <h1>
            <span class="text-semibold"><span class="material-symbols-rounded">payments</span> {{ trans('messages.payment_gateways') }}</span>
        </h1>
    </div>

@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="sub-section">
                <h2 style="margin-bottom: 10px;margin-top: 0">{{ trans('messages.payment.all_available_gateways') }}</h2>
                <p>{!! trans('messages.payment.all_available_gateways.wording') !!}</p>
                <div class="mc-list-setting mt-5">
                    @foreach ($gateways as $gateway)
                        <div class="list-setting bg-{{ $gateway->getType() }}
                            {{ $gateway->isActive() && Billing::isPaymentGatewayTypeEnabled($gateway) ? 'current' : '' }}">
                            <div class="list-setting-main" style="width: 50%">
                                <div class="title">
                                    <label>{{ $gateway->getName() }}</label>
                                </div>
                                <p>{{ $gateway->getDescription() }}</p>
                            </div>
                            <div class="list-setting-status text-nowrap pl-4">
                                @if ($gateway->isActive())
                                    @if (Billing::isPaymentGatewayTypeEnabled($gateway))
                                        <span class="label label-flat bg-active">
                                            {{ trans('messages.payment.active') }}
                                        </span>
                                    @else
                                        <span class="label label-flat bg-inactive">
                                            {{ trans('messages.payment.inactive') }}
                                        </span>
                                    @endif
                                @endif
                            </div>
                            <div class="list-setting-actions text-nowrap pl-4">
                                @if ($gateway->isActive())
                                    @if (Billing::isPaymentGatewayTypeEnabled($gateway))
                                        <a class="btn btn-secondary ml-5"
                                            link-method="post" href="{{ action('Admin\PaymentController@disable', $gateway->getType()) }}">
                                            {{ trans('messages.payment.disable') }}
                                        </a>
                                    @else
                                        <a class="btn btn-secondary ml-5"
                                            link-method="post" href="{{ action('Admin\PaymentController@enable', $gateway->getType()) }}">
                                            {{ trans('messages.payment.enable') }}
                                        </a>
                                    @endif
                                    <a class="btn btn-secondary ml-5" href="{{ $gateway->getSettingsUrl() }}">
                                        {{ trans('messages.payment.setting') }}
                                    </a>
                                @else
                                    <a class="btn btn-secondary ml-5" href="{{ $gateway->getSettingsUrl() }}">
                                        {{ trans('messages.payment.connect') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="sub-section mt-5">
        <h2>{{ trans('messages.payment.settings') }}</h2>
        <form action="{{ action('Admin\SettingController@payment') }}" method="POST" class="form-validate-jqueryz">
            {{ csrf_field() }}
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group checkbox-right-switch">
                        @include('helpers.form_control', [
                            'type' => 'checkbox',
                            'name' => 'allowed_due_subscription',
                            'value' => \Acelle\Model\Setting::get('allowed_due_subscription'),
                            'label' => trans('messages.setting.allowed_due_subscription'),
                            'help_class' => 'setting',
                            'options' => ['no', 'yes'],
                            'rules' => ['allowed_due_subscription' => 'required'],
                        ])
                    </div>

                    <div class="form-group checkbox-right-switch">
                        @include('helpers.form_control', [
                            'type' => 'checkbox',
                            'name' => 'not_require_card_for_trial',
                            'value' => \Acelle\Model\Setting::get('not_require_card_for_trial'),
                            'label' => trans('messages.setting.not_require_card_for_trial'),
                            'help_class' => 'setting',
                            'options' => ['no', 'yes'],
                            'rules' => ['not_require_card_for_trial' => 'required'],
                        ])
                    </div>
                    
                    <div class="form-group checkbox-right-switch">
                        <div class="">
                            <div style="width:100%">
                                <label class="mb-1">
                                    {{ trans('messages.setting.recurring_charge_before_days.title') }}:
                                    <span class="checkbox-description mt-1">
                                        {{ trans('messages.setting.recurring_charge_before_days.desc') }}
                                    </span>
                                </label>
                            </div>
                                    
                            <div class="d-flex align-items-center">
                                <span class="text-muted me-2">{{ trans('messages.setting.recurring_charge_before_days.before_text') }}</span>
                                <input id="recurring_charge_before_days" placeholder="" required="" value="{{ \Acelle\Model\Setting::get('recurring_charge_before_days') }}" type="number"
                                    name="recurring_charge_before_days" class="form-control required number numeric me-2"
                                    style="display:inline-block;width:60px;font-weight:bold"
                                >
                                <span class="text-muted">{{ trans('messages.setting.recurring_charge_before_days.after_text') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <hr>
            <button class="btn btn-secondary">
                {{ trans('messages.save') }}
            </a>
        </form>
    </div>
@endsection
