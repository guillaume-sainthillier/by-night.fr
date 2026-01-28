module.exports = {
    testEnvironment: 'node',
    moduleFileExtensions: ['js', 'jsx'],
    testMatch: ['**/*.test.js', '**/*.spec.js'],
    transform: {
        '^.+\\.jsx?$': 'babel-jest',
    },
    moduleNameMapper: {
        '^@/(.*)$': '<rootDir>/assets/$1',
    },
    collectCoverageFrom: ['assets/**/*.{js,jsx}', '!assets/**/*.test.{js,jsx}', '!assets/**/*.spec.{js,jsx}'],
    coverageDirectory: 'coverage',
    testPathIgnorePatterns: ['/node_modules/'],
}
