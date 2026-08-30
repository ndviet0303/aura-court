import en from './frontend/src/i18n/locales/en';
import vi from './frontend/src/i18n/locales/vi';

function compareKeys(obj1: any, obj2: any, path: string = '', missingInObj2: string[] = []) {
    for (const key in obj1) {
        const currentPath = path ? `${path}.${key}` : key;
        if (!(key in obj2)) {
            missingInObj2.push(currentPath);
        } else if (typeof obj1[key] === 'object' && typeof obj2[key] === 'object') {
            compareKeys(obj1[key], obj2[key], currentPath, missingInObj2);
        }
    }
    return missingInObj2;
}

const missingInVi = compareKeys(en, vi);
const missingInEn = compareKeys(vi, en);

console.log('Missing in VI:', missingInVi.length ? missingInVi : 'None');
console.log('Missing in EN:', missingInEn.length ? missingInEn : 'None');
