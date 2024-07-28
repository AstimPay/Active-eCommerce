<form class="form-horizontal" action="{{ route('payment_method.update') }}" method="POST">
    @csrf
    <input type="hidden" name="payment_method" value="astimpay">
    <div class="form-group row">
        <input type="hidden" name="types[]" value="ASTIMPAY_API_KEY">
        <div class="col-md-4">
            <label class="col-from-label">{{ translate('AstimPay Api Key') }}</label>
        </div>
        <div class="col-md-8">
            <input type="text" class="form-control" name="ASTIMPAY_API_KEY" value="{{ env('ASTIMPAY_API_KEY') }}"
                placeholder="{{ translate('AstimPay Api Key') }}" required>
        </div>
    </div>
    <div class="form-group row">
        <input type="hidden" name="types[]" value="ASTIMPAY_API_URL">
        <div class="col-md-4">
            <label class="col-from-label">{{ translate('AstimPay Api Url') }}</label>
        </div>
        <div class="col-md-8">
            <input type="text" class="form-control" name="ASTIMPAY_API_URL" value="{{ env('ASTIMPAY_API_URL') }}"
                placeholder="{{ translate('AstimPay Api Url') }}" required>
        </div>
    </div>
    <div class="form-group mb-0 text-right">
        <button type="submit" class="btn btn-sm btn-primary">{{ translate('Save') }}</button>
    </div>
</form>