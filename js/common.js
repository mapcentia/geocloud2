function array_unique(ar) {
    if (ar.length && typeof ar !== 'string') {
        var sorter = {};
        var out = [];
        for (var i = 0, j = ar.length; i < j; i++) {
            if (!sorter[ar[i] + typeof ar[i]]) {
                out.push(ar[i]);
                sorter[ar[i] + typeof ar[i]] = true;
            }
        }
    }
    return out || ar;
}