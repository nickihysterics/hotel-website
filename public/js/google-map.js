
var google;

function init() {
 
    var myLatlng = new google.maps.LatLng(61.355816, 63.542126);

    
    var mapOptions = {
        // Начальный уровень масштаба карты (обязательно)
        zoom: 7,

        // Координаты центра карты (обязательно)
        center: myLatlng,

        // Стиль карты.
        scrollwheel: false,
        styles: [
            {
                "featureType": "administrative.country",
                "elementType": "geometry",
                "stylers": [
                    {
                        "visibility": "simplified"
                    },
                    {
                        "hue": "#ff0000"
                    }
                ]
            }
        ]
    };

    

    // Получаем DOM-элемент, в котором будет отображаться карта.
    // Используется div с id="map".
    var mapElement = document.getElementById('map');

    // Создаем карту Google на основе элемента и настроек выше.
    var map = new google.maps.Map(mapElement, mapOptions);
    
    var addresses = ['Yugorsk'];

    for (var x = 0; x < addresses.length; x++) {
        $.getJSON('http://maps.googleapis.com/maps/api/geocode/json?address='+addresses[x]+'&sensor=false', null, function (data) {
            var p = data.results[0].geometry.location
            var latlng = new google.maps.LatLng(p.lat, p.lng);
            new google.maps.Marker({
                position: latlng,
                map: map,
                icon: 'images/loc.png'
            });

        });
    }
    
}
google.maps.event.addDomListener(window, 'load', init);
