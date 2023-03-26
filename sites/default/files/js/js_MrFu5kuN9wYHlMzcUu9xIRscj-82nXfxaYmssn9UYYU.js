var myHeaders = new Headers();
myHeaders.append("apikey", "kYFBdWs8hBulieA31UPtsbHn7s1O0ZoR");
var requestOptions = {
  method: 'GET',
  redirect: 'follow',
  headers: myHeaders
}

fetch('https://api.apilayer.com/exchangerates_data/latest?symbols=UAH&base=USD', requestOptions)
 .then(response => response.text())
 .then(result => document.getElementById("informer-main-ukraine").innerHTML = result)
 .then(response => response.json())
 .then(r => console.log(r))
 .catch(error => console.log('error', error));
;
