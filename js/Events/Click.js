import { getPathTo } from "./../Finders";
import AttributeParser from "./../AttributeParser";
import { isSameArray } from "../Helpers";

export default function click(event) {
  let tagName = event.target.tagName;
  let meta = {
    type: event.target.type || null,
  };
  let attributes = event.target.attributes;
  let parent = event.target.parentElement;

  console.log(tagName);
  if (
    tagName == "BUTTON" ||
    tagName == "A" ||
    (tagName == "INPUT" && event.target.type == "submit")
  ) {
    let target =
      event.target.value || event.target.text || event.target.innerText;
    if (!target) {
      return;
    }
    meta.label = target.trim();
  } else if (tagName == "SELECT") {
    let target = event.target.name;

    meta.label = target.trim();
  } else if (tagName == "INPUT") {
    let ignoreType = [
      "text",
      "password",
      "date",
      "email",
      "month",
      "number",
      "search",
    ];
    if (ignoreType.includes(event.target.type)) {
      return;
    }

    meta.label = event.target.name;
  } else {
    return;
  }

  // We only want to call it here because we do not want to call it with a div or anything that should be rejected on the block above.
  const parsedAttributes = AttributeParser(attributes, tagName.toLowerCase());

  if (tagName === "SELECT") {
    meta.label = event.target.value;

    let testingOutput = JSON.parse(sessionStorage.getItem("testingOutput"));
    let lastAction = testingOutput[testingOutput.length - 1];

    // In case the latest action was the same select, we don't want to add a new one,
    // just change the target meta on the previous one.
    if (
      lastAction &&
      lastAction.tag == tagName.toLowerCase() &&
      isSameArray(lastAction.attributes, parsedAttributes)
    ) {
      lastAction.meta = meta;

      sessionStorage.setItem("testingOutput", JSON.stringify(testingOutput));

      return;
    }
  } else {
    let label = event.target.labels?.[0];

    if (label) {
      meta.label = label.innerText;
    }
  }

  let finalObject = {
    action: "click",
    attributes: parsedAttributes,
    parent: parent,
    tag: tagName.toLowerCase(),
    meta: meta,
  };
  console.log(finalObject);

  MagicTest.addData(finalObject);
}
