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
