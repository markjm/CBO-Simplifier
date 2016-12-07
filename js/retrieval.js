function makePanel(data) {

    var unknown = "unknown";
    var cbo_url = data["cbo_url"] ? data["cbo_url"] : unknown;
    var title = data["title"] ? data["title"] : unknown;
    var pdf_url = data["pdf_url"] ? data["pdf_url"] : unknown;
    var summary = data["summary"] ? data["summary"] : unknown;
    var committee = data["committee"] ? data["committee"] : unknown;
    var published = data["published"] ? data["published"] : unknown;

    var panel = $('<div class="panel panel-primary"></div>');
    var header = $('<div class="panel-heading"><h3 class="panel-title"><a href="' +
        cbo_url + '" target="_blank">' + title + '</a></h3></div>');
    panel.append(header);
    var body = $('<div class="panel-body row"><div class="col-md-2"><a href="' + pdf_url +
        '" target="_blank"><img src="images/pdfimg.jpg"></a></div><div class="col-md-10"><div>' +
        summary + '</div><p></p><p class="text-muted">' + committee + '</p></div></div>');
    var positive = 0;
    var negative = 0;
    if ($.isEmptyObject(data["finanaces"])) {
        $.each(data["finances"], function(key, value) {
            var cost = value["amount"];
            if (cost > 0) {
                positive += cost;
            } else {
                negative += cost;
            }
        });
        positive = (positive).formatMoney(0);
        negative = (negative).formatMoney(0);
    } else {
        positive = unknown;
        negative = unknown;
    }

    var financials = $('<div class="col-md-4 text-muted">' + published +
        '</div><div class="col-md-4 text-success">$' + positive + '</div><div class="col-md-4 text-danger">$' + negative + '</div>');
    body.append(financials);
    panel.append(body);
    var footer = $('<div class="panel-footer"><div class="fb-like" data-href="' + cbo_url +
        '" data-layout="standard" data-action="like" data-size="small" data-show-faces="false" data-share="true"></div></div>');
    panel.append(footer);
    return panel;
}

function makeEditablePanel(data) {


    var unknown = "unknown";
    var cbo_url = data["cbo_url"] ? data["cbo_url"] : unknown;
    var title = data["title"] ? data["title"] : unknown;
    var pdf_url = data["pdf_url"] ? data["pdf_url"] : unknown;
    var summary = data["summary"] ? data["summary"] : unknown;
    var committee = data["committee"] ? data["committee"] : unknown;
    var published = data["published"] ? data["published"] : unknown;

    var panel = $('<div class="panel panel-primary"></div>');
    var header = $('<div class="panel-heading"><h3 class="panel-title" contenteditable="true"><a id="title" href="' +
        cbo_url + '" target="_blank">' + title + '</a></h3></div>');
    panel.append(header);
    var body = $('<div class="panel-body row"><div class="col-md-2"><a href="' + pdf_url +
        '" target="_blank"><img src="images/pdfimg.jpg"></a></div><div class="col-md-10"><div id="summary" contenteditable="true">' +
        summary + '</div><p></p><p class="text-muted">' + committee + '</p></div></div>');
    var positive = 0;
    var negative = 0;
    if ($.isEmptyObject(data["finanaces"])) {
        $.each(data["finances"], function(key, value) {
            var cost = value["amount"];
            if (cost > 0) {
                positive += cost;
            } else {
                negative += cost;
            }
        });
        positive = (positive).formatMoney(0);
        negative = (negative).formatMoney(0);
    } else {
        positive = unknown;
        negative = unknown;
    }

    var financials = $('<div class="col-md-4 text-muted">' + published +
        '</div><div id="positive" class="col-md-4 text-success" contenteditable="true">$' + positive + '</div><div id="negative" class="col-md-4 text-danger" contenteditable="true">$' + negative + '</div>');
    body.append(financials);
    panel.append(body);
    var footer = $('<div class="panel-footer"><button id="submit-edit" type="button" class="btn btn-default btn-block">Submit</button></div>');
    panel.append(footer);
    footer.data("confirmed", false);
    footer.click(function() {
        if (footer.data("confirmed")) {
            data["summary"] = $('#summary').html();
            data["title"] = $('#title').html();
            var pos = parseInt(invertFormatMoney($('#positive').html()));
            var neg = Math.abs(parseInt(invertFormatMoney($('#negative').html()))) * -1;
            data["finanaces"] = [{
                "timespan": 1,
                "amount": pos
            }, {
                "timespan": 1,
                "amount": neg
            }];
            $.post(apiurl + '/api.php/bills/', JSON.stringify(data));
            $(this).html("<h4 class='text-center'>Submitted, thanks!</h4>");
        } else {
            $('#submit-edit').html("Confirm?");
            footer.data("confirmed", true);
        }
    });

    $(body).click(function() {
        $('#submit-edit').html("Submit");
        footer.data("confirmed", false);
    });

    $(header).click(function() {
        $('#submit-edit').html("Submit");
        footer.data("confirmed", false);
    });

    return panel;
}

if (editable) {
    makePanel = makeEditablePanel;
}

function request() {
    $.ajax({
        url: apiurl + apiappend,
        data: {
            format: 'json'
        },
        error: function() {
            apiappend = null;
        },
        dataType: 'json',
        success: function(data) {
            $('#info #loading').remove();
            $.each(data, function(key, value) {
                if (key === "bills") {
                    $.each(data[key], function(key, value) {
                        if (!value) {
                            $('#info').append('<div class="panel panel-default panel-success"><div class="panel-heading text-center"><h3 class="panel-title">No More Bills!</h3></div></div>');
                            apiappend = null;
                        } else {
                            var list = $('#info');
                            var panel = makePanel(value);
                            list.append(panel);
                        }
                    });
                } else if (key === "next") {
                    if (value) {
                        apiappend = value;
                        $('#info').append('<div id="loading" class="panel panel-default panel-muted"><div class="panel-heading text-center"><h3 class="panel-title">Loading...</h3></div></div>');
                    } else {
                        $('#info').append('<div class="panel panel-default panel-primary"><div class="panel-heading text-center"><h3 class="panel-title">No More Bills!</h3></div></div>');
                        apiappend = null;
                    }
                } else if (key === "update") {
                    if (value) {
                        $.post(apiurl + value);
                    }
                }
            });
        },
        type: 'GET'
    });
}

function loadPage() {
    request();
    var inter = setInterval(function() {
        if ($(window).scrollTop() + $(window).height() > $(document).height() - 150) {
            if (!apiappend) {
                clearInterval(inter);
            } else {
                request();
            }
        }
    }, 1000);
}

loadPage();

$(document).ajaxComplete(function() {
    try {
        FB.XFBML.parse();
    } catch (ex) {}
});

Number.prototype.formatMoney = function(c, d, t) {
    var n = this,
        c = isNaN(c = Math.abs(c)) ? 2 : c,
        d = d == undefined ? "." : d,
        t = t == undefined ? "," : t,
        s = n < 0 ? "-" : "",
        i = String(parseInt(n = Math.abs(Number(n) || 0).toFixed(c))),
        j = (j = i.length) > 3 ? j % 3 : 0;
    return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
};

function invertFormatMoney(c) {
    return Number(c.replace(/[^0-9\.]+/g, ""));
}
