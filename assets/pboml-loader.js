const PARSER_DOMAIN = "https://pboml-parser--parseur-pboml.s3.ca-central-1.amazonaws.com/";

function loadPbomlParser() {
    window.pboml_parser_loaded = true;
    fetch(`${PARSER_DOMAIN}manifest.json`)
        .then((response) => response.json())
        .then((data) => {
            for (const property in data) {
                if (data[property].isEntry) {
                    const script = document.createElement('script');
                    script.src = `${PARSER_DOMAIN}${data[property].file}`;
                    script.type = "module";
                    document.head.appendChild(script);
                }
            }
        });
}
if (!window.pboml_parser_loaded)
    loadPbomlParser()