var myHeaders = new Headers();
myHeaders.append("apikey", "kYFBdWs8hBulieA31UPtsbHn7s1O0ZoR");
var requestOptions = {
  method: 'GET',
  redirect: 'follow',
  headers: myHeaders
}

fetch('https://api.apilayer.com/exchangerates_data/latest?symbols=UAH&base=USD', requestOptions)
 .then(response => response.json())
 .then(data => {
   const exchangeRateUsd = data.base;
   const exchangeRate = data.rates;
   document.getElementById("informer-main-ukraine").innerHTML = exchangeRate.toFixed(2);
   console.log(exchangeRate);
 })
 .catch(error => console.log('error', error));
;
