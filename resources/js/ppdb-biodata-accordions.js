const defaultSections = () => ({
    student: true,
    father: false,
    mother: false,
    guardian: false,
    siblings: false,
});

export function createPpdbBiodataAccordions() {
    return {
        sections: defaultSections(),
        toggleSection(section) {
            if (Object.prototype.hasOwnProperty.call(this.sections, section)) {
                this.sections[section] = !this.sections[section];
            }
        },
        openAllSections() {
            Object.keys(this.sections).forEach((section) => {
                this.sections[section] = true;
            });
        },
        closeAllSections() {
            Object.keys(this.sections).forEach((section) => {
                this.sections[section] = false;
            });
        },
    };
}
