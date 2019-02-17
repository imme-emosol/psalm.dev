<script>
var expandCode = function() {
    document.querySelector('body').classList.add('code_expanded');
    return false;
};

var shrinkCode = function() {
    document.querySelector('body').classList.remove('code_expanded');
    return false;
};
    
var toggleSettings = function() {
    document.querySelector('#settings_panel').classList.toggle('hidden');
    document.querySelector('#psalm_output').classList.toggle('hidden');
    return false;
};

var getLink = function() {
    fetch('/add_code.php', {
        method: 'POST',
        headers: {
            'Accept': 'application/json, application/xml, text/plain, text/html, *.*',
            'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
        },
        body: serializeJSON({code: editor.getValue()})
    })
    .then(function (response) {
        return response.text();
    })
    .then(function (response) {
        if (response.indexOf('/r/') === -1) {
            alert(response);
        } else {
            window.location = '//' + response;
        }
    });
    return false;
};

var serializeJSON = function(data) {
    return Object.keys(data).map(function (keyName) {
        return encodeURIComponent(keyName) + '=' + encodeURIComponent(data[keyName])
    }).join('&');
}

var urlParams = new URLSearchParams(window.location.search);

var latestFetch = 0;

var editor = CodeMirror.fromTextArea(document.getElementById("code"), {
    lineNumbers: true,
    matchBrackets: true,
    mode: "text/x-php",
    indentUnit: 2,
    theme: 'elegant',
    lint: {
        getAnnotations: function (code, callback, options, cm) {
            latestFetch++;
            fetchKey = latestFetch;
            fetch('/check.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json, application/xml, text/plain, text/html, *.*',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
                },
                body: serializeJSON({
                    code: code,
                    allow_phpstorm_generics: urlParams.has('allow_phpstorm_generics'),
                    strict_internal_functions: urlParams.has('strict_internal_functions')
                })
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (response) {
                if (latestFetch != fetchKey) {
                    return;
                }

                if ('results' in response) {
                    var psalm_version = response.version;
                    
                    if (psalm_version.indexOf('@')) {
                        psalm_version = psalm_version.split('@')[1];
                    }

                    var psalm_header = 'Psalm output (using commit ' + psalm_version.substring(0, 7) + '): \n\n'

                    if (response.results.length === 0) {
                        document.getElementById('psalm_output').innerText = psalm_header + 'No issues!';

                        callback([]);
                    }
                    else {
                        var text = response.results.map(
                            function (issue) {
                                return (issue.severity === 'error' ? 'ERROR' : 'INFO') + ': '
                                    + issue.type + ' - ' + issue.line_from + ':'
                                    + issue.column_from + ' - ' + issue.message;
                            }
                        );

                        document.getElementById('psalm_output').innerText = psalm_header + text.join('\n\n');

                        callback(
                            response.results.map(
                                function (issue) {
                                    return {
                                        severity: issue.severity === 'error' ? 'error' : 'warning',
                                        message: issue.message,
                                        from: cm.posFromIndex(issue.from),
                                        to: cm.posFromIndex(issue.to)
                                    };
                                }
                            )
                        );
                    }  
                }
                else if ('error' in response) {
                    var error_type = response.error.type === 'parser_error' ? 'Parser' : 'Internal Psalm';
                    document.getElementById('psalm_output').innerText = 'Psalm runner output: \n\n'
                        + error_type + ' error on line ' + response.error.line_from + ' - '
                        + response.error.message;

                    console.log(cm.posFromIndex(response.error.to));

                    callback({
                       message: response.error.message,
                       severity: 'error',
                       from: cm.posFromIndex(response.error.from),
                       to: cm.posFromIndex(response.error.to),
                    });
                }
            })
            .catch (function (error) {
                console.log('Request failed', error);
            });
        },
        async: true,
    }
});

//editor.focus();
editor.setCursor(editor.lineCount(), 0);

</script>
