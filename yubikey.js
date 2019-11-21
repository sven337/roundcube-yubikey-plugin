if (window.rcmail) {
  rcmail.addEventListener('init', function(evt) {
    otp_row = '<tr class="form-group row"> \
                 <td class="title" style="display: none;"> \
                   <label for="rcmloginyubikey">' + rcmail.get_label('roundcube_yubikey_authentication.yubikey') + '</label> \
                 </td> \
                 <td class="input input-group input-group-lg"> \
				   <span class="input-group-prepend"><i class="input-group-text">' + rcmail.get_label('roundcube_yubikey_authentication.yubikey') + ' </i></span> \
				   <input name="_yubikey" style="width: 200px;" id="rcmloginyubikey" autocomplete="off" type="text"> \
                 </td> \
               </tr>';
    document.getElementsByName('login-form')[0].getElementsByTagName('table')[0].getElementsByTagName('tbody')[0].innerHTML += otp_row;
  });
}
