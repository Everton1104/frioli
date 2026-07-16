@if(session('msg'))
    <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 999;" class="alert alert-success" role="alert">
        <strong>{{ session('msg') }}</strong> 
    </div>
    <script>
        setTimeout(function() {
            document.querySelector('.alert').remove();
        }, 2000);
    </script>
@endsession
@if(session('msgErro'))
    <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 999;" class="alert alert-danger" role="alert">
        <strong>{{ session('msgErro') }}</strong> 
    </div>
    <script>
        setTimeout(function() {
            document.querySelector('.alert').remove();
        }, 5000);
    </script>
@endsession
