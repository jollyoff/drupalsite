var myHeaders = new Headers();
myHeaders.append("apikey", "WKrZ18XNlLIklo3hvIynv4PpA1MeiBOa");
var requestOptions = {
  method: 'GET',
  redirect: 'follow',
  headers: myHeaders
}

fetch('https://api.apilayer.com/exchangerates_data/latest?symbols=UAH&base=USD', requestOptions)
 .then(response => response.json())
 .then(data => {
   const exchangeRate = data.rates.UAH;
   document.getElementById("block-currency").innerHTML = "USD -> UAH: " + exchangeRate.toFixed(3);
   console.log(exchangeRate);
 })
 .catch(error => console.log('error', error));
