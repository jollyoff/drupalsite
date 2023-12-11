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
$('#date').text(new Date().toLocaleDateString());
const currencyObj = {
    USD: 'доллар США',
    UAH: 'укр. гривна',
    EUR: 'евро',
}
$.getJSON("https://api.privatbank.ua/p24api/pubinfo?json&exchange&coursid=5", function (result) {
    console.log(result);
    $.each(result, function (i, field) {
        console.log('field', field);
        var tr = "<td>" + currencyObj[field.base_ccy] + "</td><td>" + currencyObj[field.ccy] 
        + "</td><td>" + field.buy + "</td><td>" + field.sale + "</td>";
        $("#currencyTable tbody").append("<tr>" + tr + "</tr>");
    });
});;
