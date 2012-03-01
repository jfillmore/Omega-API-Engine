/*
SHJS - Syntax Highlighting in JavaScript
Copyright (C) 2007, 2008 gnombat@users.sourceforge.net
License: http://shjs.sourceforge.net/doc/gplv3.html

5/29/2011 - Modified by Jonathon Fillmore
    * modified structure to fit into omega framework
    * encapsulated all methods into 'sh' object
    * normalized indentation
    * stripped AJAX loading feature
    * removed var declaration from loops
    * added support for non-pre HTML nodes
    * added jQuery support
*/

(function (om) {
    var shjs;
    /* Exports om.sh.highlight to add syntax highlighting to the specified
    jQuery object's HTML. */
    shjs = {
        requests: {},
        tag: 'shjs_',

        html_escape: function (str) {
            return str.replace(/\</, '&lt;').replace(/\>/, '&gt;');
        },

        isEmailAddress: function (url) {
            if (/^mailto:/.test(url)) {
                return false;
            }
            return url.indexOf('@') !== -1;
        },

        setHref: function (tags, numTags, inputString) {
            var url = inputString.substring(tags[numTags - 2].pos, tags[numTags - 1].pos);
            if (url.length >= 2 && url.charAt(0) === '<' && url.charAt(url.length - 1) === '>') {
                url = url.substr(1, url.length - 2);
            }
            if (shjs.isEmailAddress(url)) {
                url = 'mailto:' + url;
            }
            tags[numTags - 2].node.href = url;
        },

        /*
        Konqueror has a bug where the regular expression /$/g will not match at the end
        of a line more than once:

            var regex = /$/g;
            var match;

            var line = '1234567890';
            regex.lastIndex = 10;
            match = regex.exec(line);

            var line2 = 'abcde';
            regex.lastIndex = 5;
            match = regex.exec(line2);    // fails
        */
        konquerorExec: function (s) {
            var result = [''];
            result.index = s.length;
            result.input = s;
            return result;
        },

        /**
        Highlights all elements containing source code in a text string.    The return
        value is an array of objects, each representing an HTML start or end tag.    Each
        object has a property named pos, which is an integer representing the text
        offset of the tag. Every start tag also has a property named node, which is the
        DOM element started by the tag. End tags do not have this property.
        @param    inputString    a text string
        @param    language    a language definition object
        @return    an array of tag objects
        */
        highlightString: function (inputString, language) {
            if (/Konqueror/.test(navigator.userAgent)) {
                if (! language.konquered) {
                    for (var s = 0; s < language.length; s++) {
                        for (var p = 0; p < language[s].length; p++) {
                            var r = language[s][p][0];
                            if (r.source === '$') {
                            r.exec = shjs.konquerorExec;
                            }
                        }
                    }
                    language.konquered = true;
                }
            }

            var a = document.createElement('a');
            var span = document.createElement('span');

            // the result
            var tags = [];
            var numTags = 0;

            // each element is a pattern object from language
            var patternStack = [];

            // the current position within inputString
            var pos = 0;

            // the name of the current style, or null if there is no current style
            var currentStyle = null;

            var output = function(s, style) {
                var length = s.length;
                var stackLength, pattern, clone;
                // this is more than just an optimization - we don't want to output empty <span></span> elements
                if (length === 0) {
                    return;
                }
                if (! style) {
                    stackLength = patternStack.length;
                    if (stackLength !== 0) {
                        pattern = patternStack[stackLength - 1];
                        // check whether this is a state or an environment
                        if (! pattern[3]) {
                            // it's not a state - it's an environment; use the style for this environment
                            style = pattern[1];
                        }
                    }
                }
                if (currentStyle !== style) {
                    if (currentStyle) {
                        tags[numTags++] = {pos: pos};
                        if (currentStyle === shjs.tag + 'url') {
                            shjs.setHref(tags, numTags, inputString);
                        }
                    }
                    if (style) {
                        clone;
                        if (style === shjs.tag + 'url') {
                            clone = a.cloneNode(false);
                        } else {
                            clone = span.cloneNode(false);
                        }
                        clone.className = style;
                        tags[numTags++] = {node: clone, pos: pos};
                    }
                }
                pos += length;
                currentStyle = style;
            };

            var endOfLinePattern = /\r\n|\r|\n/g;
            endOfLinePattern.lastIndex = 0;
            var inputStringLength = inputString.length;
            var start, end, startOfNextLine, endOfLineMatch, line, matchCache,
                posWithinLine, stateIndex, stackLength, state, numPatterns, mc,
                bestMatch, bestPatternIndex, match, i, regex, pattern, newStyle,
                matchedString, subexpression;
            while (pos < inputStringLength) {
                start = pos;
                end;
                startOfNextLine;
                endOfLineMatch = endOfLinePattern.exec(inputString);
                if (endOfLineMatch === null) {
                    end = inputStringLength;
                    startOfNextLine = inputStringLength;
                } else {
                    end = endOfLineMatch.index;
                    startOfNextLine = endOfLinePattern.lastIndex;
                }

                line = inputString.substring(start, end);

                matchCache = [];
                for (;;) {
                    posWithinLine = pos - start;

                    stackLength = patternStack.length;
                    if (stackLength === 0) {
                        stateIndex = 0;
                    } else {
                        // get the next state
                        stateIndex = patternStack[stackLength - 1][2];
                    }

                    state = language[stateIndex];
                    numPatterns = state.length;
                    mc = matchCache[stateIndex];
                    if (! mc) {
                        mc = matchCache[stateIndex] = [];
                    }
                    bestMatch = null;
                    bestPatternIndex = -1;
                    for (i = 0; i < numPatterns; i++) {
                        match;
                        if (i < mc.length && (mc[i] === null || posWithinLine <= mc[i].index)) {
                            match = mc[i];
                        } else {
                            regex = state[i][0];
                            regex.lastIndex = posWithinLine;
                            match = regex.exec(line);
                            mc[i] = match;
                        }
                        if (match !== null && (bestMatch === null || match.index < bestMatch.index)) {
                            bestMatch = match;
                            bestPatternIndex = i;
                            if (match.index === posWithinLine) {
                                break;
                            }
                        }
                    }

                    if (bestMatch === null) {
                        output(shjs.html_escape(line.substring(posWithinLine)), null);
                        break;
                    } else {
                        // got a match
                        if (bestMatch.index > posWithinLine) {
                            output(shjs.html_escape(line.substring(posWithinLine, bestMatch.index)), null);
                        }

                        pattern = state[bestPatternIndex];

                        newStyle = pattern[1];
                        if (newStyle instanceof Array) {
                            for (subexpression = 0; subexpression < newStyle.length; subexpression++) {
                                matchedString = bestMatch[subexpression + 1];
                                output(matchedString, newStyle[subexpression]);
                            }
                        } else {
                            matchedString = bestMatch[0];
                            output(matchedString, newStyle);
                        }

                        switch (pattern[2]) {
                            case -1:
                                // do nothing
                                break;
                            case -2:
                                // exit
                                patternStack.pop();
                                break;
                            case -3:
                                // exitall
                                patternStack.length = 0;
                                break;
                            default:
                                // this was the start of a delimited pattern or a state/environment
                                patternStack.push(pattern);
                                break;
                        }
                    }
                }

                // end of the line
                if (currentStyle) {
                    tags[numTags++] = {pos: pos};
                    if (currentStyle === shjs.tag + 'url') {
                        shjs.setHref(tags, numTags, inputString);
                    }
                    currentStyle = null;
                }
                pos = startOfNextLine;
            }

            return tags;
        },

        /**
        Extracts the tags from an HTML DOM NodeList.
        @param    nodeList    a DOM NodeList
        @param    result    an object with text, tags and pos properties
        */
        extractTagsFromNodeList: function (nodeList, result) {
            var length = nodeList.length, i, node, terminator;
            for (i = 0; i < length; i++) {
                node = nodeList.item(i);
                switch (node.nodeType) {
                    case 1:
                        if (node.nodeName.toLowerCase() === 'br') {
                            if (/MSIE/.test(navigator.userAgent)) {
                                terminator = '\r';
                            } else {
                                terminator = '\n';
                            }
                            result.text.push(terminator);
                            result.pos++;
                        } else {
                            result.tags.push({node: node.cloneNode(false), pos: result.pos});
                            shjs.extractTagsFromNodeList(node.childNodes, result);
                            result.tags.push({pos: result.pos});
                        }
                        break;
                    case 3:
                    case 4:
                        result.text.push(node.data);
                        result.pos += node.length;
                        break;
                }
            }
        },

        /**
        Extracts the tags from the text of an HTML element. The extracted tags will be
        returned as an array of tag objects. See shjs.highlightString for the format of
        the tag objects.
        @param    element    a DOM element
        @param    tags    an empty array; the extracted tag objects will be returned in it
        @return    the text of the element
        @see    shjs.highlightString
        */
        extractTags: function (element, tags) {
            var result = {};
            result.text = [];
            result.tags = tags;
            result.pos = 0;
            shjs.extractTagsFromNodeList(element.childNodes, result);
            return result.text.join('');
        },

        /**
        Merges the original tags from an element with the tags produced by highlighting.
        @param    originalTags    an array containing the original tags
        @param    highlightTags    an array containing the highlighting tags - these must not overlap
        @result    an array containing the merged tags
        */
        mergeTags: function (originalTags, highlightTags) {
            var numOriginalTags = originalTags.length;
            if (numOriginalTags === 0) {
                return highlightTags;
            }

            var numHighlightTags = highlightTags.length;
            if (numHighlightTags === 0) {
                return originalTags;
            }

            var result = [];
            var originalIndex = 0;
            var highlightIndex = 0;

            var originalIndex, highlightTag;
            while (originalIndex < numOriginalTags && highlightIndex < numHighlightTags) {
                originalTag = originalTags[originalIndex];
                highlightTag = highlightTags[highlightIndex];

                if (originalTag.pos <= highlightTag.pos) {
                    result.push(originalTag);
                    originalIndex++;
                } else {
                    result.push(highlightTag);
                    if (highlightTags[highlightIndex + 1].pos <= originalTag.pos) {
                        highlightIndex++;
                        result.push(highlightTags[highlightIndex]);
                        highlightIndex++;
                    } else {
                        // new end tag
                        result.push({pos: originalTag.pos});

                        // new start tag
                        highlightTags[highlightIndex] = {node: highlightTag.node.cloneNode(false), pos: originalTag.pos};
                    }
                }
            }
            while (originalIndex < numOriginalTags) {
                result.push(originalTags[originalIndex]);
                originalIndex++;
            }
            while (highlightIndex < numHighlightTags) {
                result.push(highlightTags[highlightIndex]);
                highlightIndex++;
            }
            return result;
        },

        /**
        Inserts tags into text.
        @param    tags    an array of tag objects
        @param    text    a string representing the text
        @return    a DOM DocumentFragment representing the resulting HTML
        */
        insertTags: function (tags, text) {
            var doc = document;

            var result = document.createDocumentFragment();
            var tagIndex = 0;
            var numTags = tags.length;
            var textPos = 0;
            var textLength = text.length;
            var currentNode = result;

            var tag, tagPos, newNode;
            // output one tag or text node every iteration
            while (textPos < textLength || tagIndex < numTags) {
                if (tagIndex < numTags) {
                    tag = tags[tagIndex];
                    tagPos = tag.pos;
                } else {
                    tagPos = textLength;
                }
                if (tagPos <= textPos) {
                    // output the tag
                    if (tag.node) {
                        // start tag
                        newNode = tag.node;
                        currentNode.appendChild(newNode);
                        currentNode = newNode;
                    } else {
                        // end tag
                        currentNode = currentNode.parentNode;
                    }
                    tagIndex++;
                } else {
                    // output text
                    currentNode.appendChild(doc.createTextNode(text.substring(textPos, tagPos)));
                    textPos = tagPos;
                }
            }
            return result;
        },

        /**
        Highlights an element containing source code.    Upon completion of this function,
        the element will have been placed in the "shjs.sourceCode" class.
        @param    element    a DOM element containing the source code to be highlighted
        @param    language    a language definition object
        */
        highlightElement: function (element, language) {
            $(element).toggleClass(shjs.tag + 'sourceCode', true);
            var originalTags = [];
            var inputString = shjs.extractTags(element, originalTags);
            var highlightTags = shjs.highlightString(inputString, language);
            var tags = shjs.mergeTags(originalTags, highlightTags);
            var documentFragment = shjs.insertTags(tags, inputString);
            while (element.hasChildNodes()) {
                element.removeChild(element.firstChild);
            }
            element.appendChild(documentFragment);
        }
    };
    om.sh = {
        languages: {},
        /**
        Highlights all elements matching the specified jquery selector
        */
        highlight: function (jquery, language) {
            jquery.each(function () {
                var node;
                node = $(this);
                if (language in om.sh.languages) {
                    shjs.highlightElement(this, om.sh.languages[language]);
                } else {
                    throw 'No support for the language "' + language + '" available.';
                }
            });
        }
    };
}(om));
