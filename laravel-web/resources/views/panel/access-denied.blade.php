@extends('panel.layouts.app')

@section('title', 'Access Denied')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="table-card text-center py-5">
            <div class="mx-auto mb-4 d-flex align-items-center justify-content-center" style="width:92px;height:92px;border-radius:30px;background:rgba(255,77,79,.1);color:var(--danger);font-size:44px;">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h3 style="font-family:'Space Grotesk';font-weight:800;">Access Denied</h3>
            <p class="text-muted mb-4">Your staff role does not have permission to open this module. Please contact the gym owner if you need access.</p>

            @if(!empty($requiredPermissions))
                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                    @foreach($requiredPermissions as $permission)
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ $permission }}</span>
                    @endforeach
                </div>
            @endif

            <button type="button" onclick="history.back()" class="btn btn-primary"><i class="bi bi-arrow-left me-2"></i>Go Back</button>
        </div>
    </div>
</div>
@endsection
