@if ($plans->total())
    <a href="{{ action('Admin\PlanController@index', [
        'keyword' => request()->keyword
    ]) }}" class="search-head border-bottom d-block">
        <div class="d-flex">
            <div class="me-auto">
                <label class="fw-600">
                    <span class="material-symbols-rounded me-1">fact_check </span> {{ trans('messages.plans') }}
                </label>
            </div>
            <div>
                {{ $plans->count() }} / {{ $plans->total() }} · {{ trans('messages.search.view_all') }}
            </div>
        </div>
    </a>
    @foreach($plans as $plan)
        <a href="{{ action('Admin\PlanController@general', $plan->uid) }}" class="search-result border-bottom d-block">
            <div class="d-flex align-items-center">
                <div>
                    <svg class="svg-fill-current-all me-3" style="width: 26px;height:26px" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 126.3 124.4" xml:space="preserve"><style type="text/css">.st0{fill:#333333;}</style><g id="Layer_2_1_"><g id="Layer_1-2"><path class="st0" d="M26.4,113.3c-7,0-13.6-2.7-18.6-7.7C2.8,100.7,0,94.1,0,87c0,0,0-0.1,0-0.1V32C0.1,17.4,12,5.6,26.5,5.6h36.3 c1.9,0,3.5,1.6,3.5,3.5s-1.6,3.5-3.5,3.5H26.5C15.8,12.6,7,21.3,7,32v54.9c0,5.3,2.1,10.1,5.7,13.8s8.6,5.7,13.7,5.6h50.1 c10.7,0,19.4-8.7,19.4-19.4V61c0-1.9,1.6-3.5,3.5-3.5s3.5,1.6,3.5,3.5v25.9c0,14.6-11.8,26.4-26.4,26.4H26.5 C26.5,113.3,26.4,113.3,26.4,113.3z"></path><path class="st0" d="M51.5,60.9c-9.3,0-16.8-7.5-16.8-16.8s7.5-16.8,16.8-16.8s16.8,7.5,16.8,16.8S60.8,60.9,51.5,60.9z M51.5,34.3c-5.4,0-9.8,4.4-9.8,9.8s4.4,9.8,9.8,9.8s9.8-4.4,9.8-9.8S56.9,34.3,51.5,34.3z"></path><path class="st0" d="M77.9,77.3H25.1c-1.9,0-3.5-1.6-3.5-3.5s1.6-3.5,3.5-3.5h52.8c1.9,0,3.5,1.6,3.5,3.5S79.8,77.3,77.9,77.3z"></path><path class="st0" d="M77.9,91.6H25.1c-1.9,0-3.5-1.6-3.5-3.5s1.6-3.5,3.5-3.5h52.8c1.9,0,3.5,1.6,3.5,3.5S79.8,91.6,77.9,91.6z"></path><path class="st0" d="M82,54.9c-0.7,0-1.4-0.2-2.1-0.7c-1.1-0.8-1.6-2.1-1.4-3.4l2.7-15.6L70,24.3c-1-0.9-1.3-2.3-0.9-3.6 c0.4-1.3,1.5-2.2,2.8-2.4l15.6-2.2l7-14.1c0.6-1.2,1.8-2,3.1-2s2.5,0.8,3.1,2l7,14.1l15.6,2.2c1.3,0.2,2.4,1.1,2.8,2.4 c0.4,1.3,0.1,2.7-0.9,3.6L114,35.2l2.7,15.6c0.2,1.3-0.3,2.6-1.4,3.4c-1.1,0.8-2.5,0.9-3.7,0.3l-14-7.3l-14,7.3 C83.1,54.8,82.6,54.9,82,54.9z M97.6,39.7c0.6,0,1.1,0.1,1.6,0.4l9.3,4.9l-1.8-10.4c-0.2-1.1,0.2-2.3,1-3.1l7.5-7.2l-10.3-1.5 c-1.1-0.2-2.1-0.9-2.6-1.9l-4.7-9.4l-4.7,9.4c-0.5,1-1.5,1.8-2.6,1.9L80,24.2l7.5,7.2c0.8,0.8,1.2,2,1,3.1L86.7,45l9.3-4.9 C96.5,39.8,97,39.7,97.6,39.7z"></path><path class="st0" d="M86.5,124.4H80c-1.9,0-3.5-1.6-3.5-3.5s1.6-3.5,3.5-3.5h6.5c22.7,0,23.9-20,23.9-24V76.2 c0-1.9,1.6-3.5,3.5-3.5s3.5,1.6,3.5,3.5v17.2C117.4,108.8,107.8,124.4,86.5,124.4z"></path></g></g></svg>
                </div>
                <div>
                    <label class="fw-600 text-nowrap">
                        {{ $plan->name }} ({{ \Acelle\Library\Tool::format_price($plan->price, $plan->currency->format) }})
                    </label>
                    <p class="desc text-muted mt-1 mb-0 text-nowrap">
                        <span class="fw-600">{{ $plan->displayTotalQuota() }} </span>
                        {{ trans('messages.sending_total_quota_label') }}
                         · 
                        <span class="fw-600">{{ trans('messages.plan_status_' . $plan->status) }}</p>
                </div>
            </div>
                
        </a>
    @endforeach
@endif