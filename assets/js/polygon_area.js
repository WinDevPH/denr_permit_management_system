/**
 * Geodesic polygon area from lat/lng points (WGS84).
 * @param {Array<{lat:number,lng:number}>|Array<[number,number]>} points
 * @returns {number|null} Area in hectares, or null if fewer than 3 points
 */
function denrPolygonAreaHectares(points) {
  if (!points || points.length < 3) return null;

  var R = 6378137;
  var total = 0;
  var n = points.length;

  for (var i = 0; i < n; i++) {
    var p1 = points[i];
    var p2 = points[(i + 1) % n];
    var lat1 = (Number(p1.lat != null ? p1.lat : p1[0]) * Math.PI) / 180;
    var lat2 = (Number(p2.lat != null ? p2.lat : p2[0]) * Math.PI) / 180;
    var dLon = ((Number(p2.lng != null ? p2.lng : p2[1]) - Number(p1.lng != null ? p1.lng : p1[1])) * Math.PI) / 180;
    total += dLon * (2 + Math.sin(lat1) + Math.sin(lat2));
  }

  var sqM = Math.abs((total * R * R) / 2);
  return sqM / 10000;
}

/**
 * @param {Array<{lat:number,lng:number}>|Array<[number,number]>} points
 * @param {number} [decimals=4]
 * @returns {string}
 */
function denrFormatBoundaryArea(points, decimals) {
  var ha = denrPolygonAreaHectares(points);
  if (ha == null) return "";
  var d = typeof decimals === "number" ? decimals : 4;
  var sqM = ha * 10000;
  return ha.toFixed(d) + " ha (" + Math.round(sqM).toLocaleString() + " m\u00b2)";
}
